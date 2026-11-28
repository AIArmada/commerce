<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Drivers;

use AIArmada\Jnt\Contracts\TrackingReader;
use AIArmada\Jnt\Data\AddressData as JntAddressData;
use AIArmada\Jnt\Data\GoodsType;
use AIArmada\Jnt\Data\ItemData;
use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Data\TrackingStatus as JntTrackingStatus;
use AIArmada\Jnt\Data\TypeTransformer;
use AIArmada\Jnt\Enums\CancellationReason;
use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Exceptions\JntNetworkException;
use AIArmada\Jnt\Services\JntExpressService;
use AIArmada\Jnt\Support\ShippingRateCalculator;
use AIArmada\Shipping\Contracts\AddressValidationResult;
use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\CarrierOperationResult;
use AIArmada\Shipping\Data\DriverCapability;
use AIArmada\Shipping\Data\LabelData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Data\ShipmentItemData;
use AIArmada\Shipping\Data\ShippingMethodData;
use AIArmada\Shipping\Data\TrackingData;
use AIArmada\Shipping\Data\TrackingEventData;
use AIArmada\Shipping\Data\TrackingStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * J&T Express shipping driver implementation.
 *
 * Integrates J&T Express with the unified shipping abstraction layer.
 */
class JntShippingDriver implements ShippingDriverInterface
{
    public function __construct(
        protected readonly JntExpressService $jntService,
        protected readonly TrackingReader $trackingService,
    ) {}

    public function getCarrierCode(): string
    {
        return 'jnt';
    }

    public function getCarrierName(): string
    {
        return 'J&T Express';
    }

    public function supports(DriverCapability $capability): bool
    {
        return match ($capability) {
            DriverCapability::RateQuotes => true,
            DriverCapability::LabelGeneration => true,
            DriverCapability::Tracking => true,
            DriverCapability::Webhooks => true,
            DriverCapability::CashOnDelivery => true,
            DriverCapability::BatchOperations => true,
            DriverCapability::Returns => false,
            DriverCapability::AddressValidation => false,
            DriverCapability::PickupScheduling => true,
            DriverCapability::InsuranceClaims => false,
            DriverCapability::MultiPackage => false,
            DriverCapability::InternationalShipping => false,
        };
    }

    public function getAvailableMethods(): array
    {
        return [
            new ShippingMethodData(
                code: 'EZ',
                name: 'J&T EZ',
                description: 'Standard delivery',
                minDays: 2,
                maxDays: 4,
                trackingAvailable: true,
            ),
            new ShippingMethodData(
                code: 'EXPRESS',
                name: 'J&T Express',
                description: 'Express delivery',
                minDays: 1,
                maxDays: 2,
                trackingAvailable: true,
            ),
        ];
    }

    /**
     * @param  array<PackageData>  $packages
     * @param  array<string, mixed>  $options
     * @return list<RateQuoteData>
     */
    public function getRates(
        AddressData $origin,
        AddressData $destination,
        array $packages,
        array $options = []
    ): array {
        $totalWeight = 0;

        foreach ($packages as $package) {
            if ($package->quantity < 1 || $package->weight < 0) {
                throw new InvalidArgumentException('Shipping package weight and quantity must not be negative.');
            }

            if ($package->weight > 0 && $package->quantity > intdiv(PHP_INT_MAX - $totalWeight, $package->weight)) {
                throw new InvalidArgumentException('Shipping package weight exceeds the supported range.');
            }

            $totalWeight += $package->weight * $package->quantity;
        }

        $quote = ShippingRateCalculator::quote($totalWeight, $destination->postcode, $destination->state);

        return [
            new RateQuoteData(
                carrier: 'jnt',
                service: 'EZ',
                rate: $quote['amount'],
                currency: 'MYR',
                estimatedDays: $quote['estimated_days'],
                serviceDescription: 'J&T EZ - Standard Delivery',
                calculatedLocally: true,
            ),
        ];
    }

    public function createShipment(ShipmentData $data): CarrierOperationResult
    {
        try {
            $sender = $this->convertToJntAddress($data->origin, 'sender');
            $receiver = $this->convertToJntAddress($data->destination, 'receiver');
            $items = $this->convertToJntItems($data->items);
            $packageInfo = $this->createPackageInfo($data);

            $orderData = $this->jntService->createOrder(
                sender: $sender,
                receiver: $receiver,
                items: $items,
                packageInfo: $packageInfo,
                orderId: $data->reference,
                additionalData: array_filter([
                    'codInfo' => $data->isCashOnDelivery()
                        ? ['codValue' => TypeTransformer::forMoney($data->codAmount)]
                        : null,
                    'remark' => $data->instructions ?? '',
                ], static fn (mixed $value): bool => $value !== null),
            );

            $trackingNumber = $orderData->trackingNumber;

            return CarrierOperationResult::succeeded(
                trackingNumber: $trackingNumber,
                carrierReference: $orderData->orderId,
            );
        } catch (JntNetworkException $e) {
            return CarrierOperationResult::unknown($e->getMessage());
        } catch (JntApiException $e) {
            return CarrierOperationResult::failed(
                error: $e->getMessage(),
                retryable: false,
            );
        } catch (Throwable $e) {
            return CarrierOperationResult::failed(
                error: $e->getMessage(),
                retryable: true,
            );
        }
    }

    public function cancelShipment(string $trackingNumber): CarrierOperationResult
    {
        try {
            $orderId = null;

            try {
                $tracking = $this->jntService->trackParcel(null, $trackingNumber);
                $orderId = $tracking->orderId;
            } catch (Throwable) {
                // ponytail: best-effort orderId resolution
            }

            $response = $this->jntService->cancelOrder(
                orderId: $orderId ?? $trackingNumber,
                reason: CancellationReason::CUSTOMER_REQUEST,
                trackingNumber: $trackingNumber,
            );

            $success = array_key_exists('success', $response)
                ? (bool) $response['success']
                : true;

            if ($success) {
                return CarrierOperationResult::succeeded();
            }

            return CarrierOperationResult::failed(error: 'Carrier rejected cancellation');
        } catch (JntNetworkException $e) {
            return CarrierOperationResult::unknown($e->getMessage());
        } catch (JntApiException $e) {
            return CarrierOperationResult::failed(
                error: $e->getMessage(),
                retryable: false,
            );
        } catch (Throwable $e) {
            return CarrierOperationResult::unknown($e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function generateLabel(string $trackingNumber, array $options = []): LabelData
    {
        $orderId = $options['order_id'] ?? $options['orderId'] ?? $trackingNumber;

        $response = $this->jntService->printOrder(
            orderId: (string) $orderId,
            trackingNumber: $trackingNumber,
            templateName: $options['template'] ?? null,
        );

        $base64Content = $response['base64EncodeContent'] ?? null;

        return new LabelData(
            format: 'pdf',
            url: null,
            content: $base64Content,
            trackingNumber: $trackingNumber,
        );
    }

    public function track(string $trackingNumber): TrackingData
    {
        $response = $this->trackingService->track(trackingNumber: $trackingNumber);

        $events = collect($response['events'] ?? [])
            ->map(function (array $event) {
                /** @var JntTrackingStatus $jntStatus */
                $jntStatus = $event['status'];
                $normalizedStatus = $this->mapJntStatusToNormalized($jntStatus);

                /** @var CarbonImmutable $occurredAt */
                $occurredAt = $event['occurred_at'];

                return new TrackingEventData(
                    code: $jntStatus->value,
                    description: $event['description'],
                    timestamp: $occurredAt->toDateTimeImmutable(),
                    normalizedStatus: $normalizedStatus,
                    location: $event['location'] ?? null,
                    raw: $event,
                );
            })
            ->sortBy(static fn (TrackingEventData $event): int => $event->timestamp->getTimestamp())
            ->values();

        /** @var JntTrackingStatus $currentJntStatus */
        $currentJntStatus = $response['current_status'];
        $currentStatus = $this->mapJntStatusToNormalized($currentJntStatus);
        $latestEvent = $events->last();
        $deliveredEvent = $events->last(
            static fn (TrackingEventData $event): bool => $event->normalizedStatus === TrackingStatus::Delivered
        );

        return new TrackingData(
            trackingNumber: $trackingNumber,
            status: $currentStatus,
            events: $events->all(),
            carrier: 'jnt',
            deliveredAt: $currentStatus === TrackingStatus::Delivered
            ? ($deliveredEvent ?? $latestEvent)?->timestamp
            : null,
        );
    }

    public function validateAddress(AddressData $address): AddressValidationResult
    {
        // JNT doesn't provide address validation API
        return new AddressValidationResult(
            valid: true,
            warnings: ['Address validation not available for J&T Express.'],
        );
    }

    public function servicesDestination(AddressData $destination): bool
    {
        // JNT services Malaysia
        return in_array(mb_strtoupper($destination->country), ['MY', 'MYS'], true);
    }

    /**
     * Convert shipping address data to JNT format.
     */
    protected function convertToJntAddress(AddressData $address, string $type): JntAddressData
    {
        return new JntAddressData(
            name: $address->name,
            phone: $address->phone,
            address: $address->line1,
            postCode: $address->postcode,
            city: $address->city ?? '',
            area: $address->state ?? '',
        );
    }

    /**
     * Convert shipment items to JNT format.
     *
     * @param  array<ShipmentItemData>  $items
     * @return array<ItemData>
     */
    protected function convertToJntItems(array $items): array
    {
        return array_map(function ($item) {
            return new ItemData(
                name: $item->name,
                quantity: $item->quantity,
                weight: $item->weight ?? 0,
                priceMinor: $item->declaredValue ?? 0,
            );
        }, $items);
    }

    /**
     * Create package info from shipment data.
     */
    protected function createPackageInfo(ShipmentData $data): PackageInfoData
    {
        return new PackageInfoData(
            quantity: $data->packages === [] ? 1 : array_sum(array_column($data->packages, 'quantity')),
            weight: round($data->getTotalWeight() / 1000, 2),
            valueMinor: $data->declaredValue ?? 0,
            goodsType: GoodsType::PACKAGE,
        );
    }

    /**
     * Map JNT tracking status to normalized shipping status.
     */
    protected function mapJntStatusToNormalized(JntTrackingStatus $jntStatus): TrackingStatus
    {
        return match ($jntStatus) {
            JntTrackingStatus::Pending => TrackingStatus::LabelCreated,
            JntTrackingStatus::PickedUp => TrackingStatus::PickedUp,
            JntTrackingStatus::InTransit => TrackingStatus::InTransit,
            JntTrackingStatus::AtHub => TrackingStatus::ArrivedAtFacility,
            JntTrackingStatus::OutForDelivery => TrackingStatus::OutForDelivery,
            JntTrackingStatus::DeliveryAttempted => TrackingStatus::DeliveryAttemptFailed,
            JntTrackingStatus::Delivered => TrackingStatus::Delivered,
            JntTrackingStatus::ReturnInitiated => TrackingStatus::ReturnToSender,
            JntTrackingStatus::Returned => TrackingStatus::ReturnDelivered,
            JntTrackingStatus::Exception => TrackingStatus::OnHold,
        };
    }
}
