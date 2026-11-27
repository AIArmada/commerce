<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Notifications\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Events\JntOrderStatusChanged;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use AIArmada\Jnt\Features\Tracking\Actions\JntTrackingService;
use AIArmada\Jnt\Notifications\OrderDeliveredNotification;
use AIArmada\Jnt\Notifications\OrderProblemNotification;
use AIArmada\Jnt\Notifications\OrderShippedNotification;
use AIArmada\Jnt\Notifications\ShipmentEmailRecipient;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;
use TypeError;
use ValueError;

final class NotifyShipmentStatusChanged
{
    public function __construct(
        private readonly JntTrackingService $trackingService
    ) {}

    /**
     * Handle the event.
     */
    public function execute(JntOrderStatusChanged $event): void
    {
        if (! config('jnt.notifications.enabled', true)) {
            return;
        }

        $owner = $event->owner();

        OwnerContext::withOwner($owner, function () use ($event): void {
            $order = $event->resolveOrder();

            if ($order === null) {
                return;
            }

            $notifiable = $this->resolveNotifiable($order);

            if ($notifiable === null) {
                return;
            }

            $notification = $this->createNotification($event, $order);

            if ($notification !== null) {
                try {
                    Notification::send($notifiable, $notification);
                } catch (Throwable $e) {
                    if (! $e instanceof TypeError && ! $e instanceof ValueError) {
                        throw $e;
                    }

                    Log::channel(config('jnt.logging.channel', 'stack'))
                        ->warning('J&T shipment notification skipped: unusable notifiable', [
                            'order_id' => $order->order_id,
                            'error' => $e->getMessage(),
                        ]);
                }
            }
        });
    }

    /**
     * Resolve the notifiable entity from the order.
     */
    private function resolveNotifiable(JntOrder $order): ?object
    {
        // Try owner relationship
        $owner = $order->owner()->getResults();

        if ($owner !== null) {
            if (! in_array(Notifiable::class, class_uses_recursive($owner), true)
                && ! method_exists($owner, 'routeNotificationForMail')) {
                Log::channel(config('jnt.logging.channel', 'stack'))
                    ->warning('J&T shipment notification skipped: owner is not notifiable', [
                        'order_id' => $order->order_id,
                        'owner_type' => $order->owner_type,
                    ]);

                return null;
            }

            return $owner;
        }

        // Try to get notifiable from metadata
        $metadata = $order->metadata ?? [];
        $email = $metadata['notification_email'] ?? null;

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return new ShipmentEmailRecipient($email);
    }

    /**
     * Create the appropriate notification based on status change.
     */
    private function createNotification(JntOrderStatusChanged $event, JntOrder $order): OrderShippedNotification | OrderDeliveredNotification | OrderProblemNotification | null
    {
        $trackingData = $this->getTrackingData($order);

        if ($trackingData === null) {
            return null;
        }

        return match ($event->currentStatus) {
            TrackingStatus::PickedUp,
            TrackingStatus::InTransit,
            TrackingStatus::AtHub => new OrderShippedNotification(
                tracking: $trackingData,
                estimatedDelivery: $this->getEstimatedDelivery($order)
            ),
            TrackingStatus::OutForDelivery => new OrderShippedNotification(
                tracking: $trackingData,
                estimatedDelivery: CarbonImmutable::now()->format('Y-m-d')
            ),
            TrackingStatus::Delivered => new OrderDeliveredNotification(
                tracking: $trackingData
            ),
            TrackingStatus::DeliveryAttempted,
            TrackingStatus::ReturnInitiated,
            TrackingStatus::Returned,
            TrackingStatus::Exception => new OrderProblemNotification(
                tracking: $trackingData,
                supportContact: config('jnt.notifications.support_contact')
            ),
            default => null,
        };
    }

    /**
     * Get tracking data for the order.
     */
    private function getTrackingData(JntOrder $order): ?TrackingData
    {
        try {
            $result = $this->trackingService->track(
                orderId: $order->order_id,
                trackingNumber: $order->tracking_number
            );

            $details = array_map(
                static fn (array $event): TrackingDetailData => $event['raw'],
                $result['events']
            );

            return new TrackingData(
                trackingNumber: $result['tracking_number'],
                orderId: $result['order_id'],
                details: array_values($details)
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get estimated delivery date for the order.
     */
    private function getEstimatedDelivery(JntOrder $order): ?string
    {
        // Calculate from default days since JntOrder doesn't have estimated_delivery_at
        $days = config('jnt.shipping.default_estimated_days', 3);

        return CarbonImmutable::now()->addDays($days)->format('Y-m-d');
    }
}
