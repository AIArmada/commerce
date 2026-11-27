<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Jnt\Features\Tracking\Api\ReadTrackingStatus;
use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingStatus;
use AIArmada\Jnt\Events\JntOrderStatusChanged;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use AIArmada\Jnt\Features\Orders\Api\UpdateTrackingSnapshot;
use AIArmada\Jnt\Features\Tracking\Api\StoreTrackingEvents;
use AIArmada\Jnt\Services\JntExpressService;
use AIArmada\Jnt\Support\TrackingEventHash;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SyncOrderTracking
{
    public function __construct(
        private readonly JntExpressService $expressService,
        private readonly ReadTrackingStatus $trackingStatus,
    ) {}

    /**
     * Sync tracking events to the database for a JntOrder
     */
    public function syncOrderTracking(JntOrder $order): JntOrder
    {
        if (JntOrder::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(JntOrder::class, (string) $order->getKey());
        }

        $owner = OwnerContext::fromTypeAndId($order->owner_type, $order->owner_id);

        if (($order->owner_type !== null || $order->owner_id !== null) && $owner === null) {
            throw new AuthorizationException('The tracked order owner cannot be resolved.');
        }

        return OwnerContext::withOwner($owner, function () use ($order): JntOrder {
            $trackingNumber = $order->tracking_number;

            if ($trackingNumber === null) {
                return $order;
            }

            $trackingData = $this->expressService->trackParcel(trackingNumber: $trackingNumber);

            return DB::transaction(function () use ($order, $trackingNumber, $trackingData): JntOrder {
                $locked = JntOrder::query()->withoutOwnerScope()->lockForUpdate()->findOrFail($order->getKey());

                if ($locked->owner_type !== $order->owner_type || $locked->owner_id !== $order->owner_id || $locked->tracking_number !== $trackingNumber) {
                    throw new AuthorizationException('The tracked order changed during synchronization.');
                }

                $order = $locked;

                $this->insertMissingSyncEvents($order, $trackingNumber, $trackingData);

                // Update order status
                $currentStatus = $this->trackingStatus->getCurrentStatus($trackingData);
                $previousStatusCode = $order->last_status_code;
                $latestDetail = $trackingData->getLatestDetail();

                if ($latestDetail !== null) {
                    $order->status = $currentStatus->value;
                    $order->last_status_code = $latestDetail->scanTypeCode;
                    $order->last_status = $latestDetail->description;
                    $order->last_tracked_at = CarbonImmutable::now();

                    // Set lifecycle timestamps based on tracking events
                    if ($latestDetail->problemType !== null) {
                        if ($order->problem_at === null) {
                            $order->problem_at = CarbonImmutable::now();
                        }
                    }

                    if ($currentStatus === TrackingStatus::Exception) {
                        if ($order->problem_at === null) {
                            $order->problem_at = CarbonImmutable::now();
                        }
                        if ($order->exception_at === null) {
                            $order->exception_at = CarbonImmutable::now();
                        }
                    }

                    if ($currentStatus === TrackingStatus::Returned && $order->returned_at === null) {
                        $order->returned_at = CarbonImmutable::now();
                    }

                    if ($latestDetail->problemType === null && $currentStatus !== TrackingStatus::Exception && $order->problem_at !== null) {
                        $order->resolved_at = CarbonImmutable::now();
                        $order->problem_at = null;
                    }

                    // Mark as delivered if appropriate
                    if ($currentStatus === TrackingStatus::Delivered && $order->delivered_at === null) {
                        $deliveredAt = $this->trackingStatus->parseDetailScanTime($latestDetail);

                        if ($deliveredAt !== null) {
                            $order->delivered_at = $deliveredAt;
                        }
                    }
                }

                app(UpdateTrackingSnapshot::class)->handle(
                    (string) $order->getKey(),
                    $order->getDirty(),
                    $latestDetail === null ? null : $this->trackingStatus->parseDetailScanTime($latestDetail),
                );
                $order->refresh();

                // Fire status changed event if status actually changed
                if ($previousStatusCode !== $order->last_status_code) {
                    $statusChanged = new JntOrderStatusChanged(
                        (string) $order->getKey(),
                        $order->order_id,
                        $order->tracking_number,
                        $order->owner_type,
                        $order->owner_id,
                        $order->getNormalizedStatus(),
                        $previousStatusCode,
                    );

                    DB::afterCommit(static fn () => event($statusChanged));
                }

                return $order->fresh() ?? $order;
            });
        });
    }

    /**
     * Batch sync tracking for multiple orders
     *
     * @param  iterable<JntOrder>  $orders
     * @return array{successful: array<JntOrder>, failed: array<array{order: JntOrder, error: string}>}
     */
    public function batchSyncTracking(iterable $orders): array
    {
        $successful = [];
        $failed = [];

        foreach ($orders as $order) {
            try {
                $successful[] = $this->syncOrderTracking($order);
            } catch (Throwable $e) {
                $failed[] = [
                    'order' => $order,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
        ];
    }

    /**
     * Get orders that need tracking updates
     *
     * @return Collection<int, JntOrder>
     */
    public function getOrdersNeedingTrackingUpdate(int $limit = 100): Collection
    {
        if (! (bool) config('jnt.owner.enabled', false)) {
            return JntOrder::query()
                ->whereNotNull('tracking_number')
                ->whereNull('delivered_at')
                ->where(function ($query): void {
                    $query->whereNull('last_tracked_at')
                        ->orWhere('last_tracked_at', '<', CarbonImmutable::now()->subHours(1));
                })
                ->orderBy('last_tracked_at', 'asc')
                ->limit($limit)
                ->get();
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            throw new AuthorizationException('JNT tracking updates require an explicit owner context when jnt.owner.enabled is true.');
        }

        $includeGlobal = (bool) config('jnt.owner.include_global', false);

        return $this->getOrdersNeedingTrackingUpdateForOwner(owner: $owner, includeGlobal: $includeGlobal, limit: $limit);

    }

    /**
     * Get orders that need tracking updates for a specific owner.
     *
     * NOTE: Non-request surfaces must not rely on ambient auth; pass/iterate owners explicitly.
     *
     * @return Collection<int, JntOrder>
     */
    public function getOrdersNeedingTrackingUpdateForOwner(?Model $owner, bool $includeGlobal = false, int $limit = 100): Collection
    {
        $includeGlobal = $includeGlobal && (bool) config('jnt.owner.include_global', false);

        /** @var Builder<JntOrder> $query */
        $query = JntOrder::query()->forOwner(owner: $owner, includeGlobal: $includeGlobal);

        return $query
            ->whereNotNull('tracking_number')
            ->whereNull('delivered_at')
            ->where(function ($query): void {
                $query->whereNull('last_tracked_at')
                    ->orWhere('last_tracked_at', '<', CarbonImmutable::now()->subHours(1));
            })
            ->orderBy('last_tracked_at', 'asc')
            ->limit($limit)
            ->get();
    }

    private function insertMissingSyncEvents(JntOrder $order, string $trackingNumber, TrackingData $trackingData): void
    {
        $rows = [];

        foreach ($trackingData->details as $detail) {
            $scanTime = $this->trackingStatus->parseDetailScanTime($detail);

            if ($scanTime === null) {
                continue;
            }

            $rows[] = [
                'id' => (string) Str::orderedUuid(),
                'event_hash' => TrackingEventHash::forIdentity(
                    orderId: (string) $order->id,
                    trackingNumber: $trackingNumber,
                    scanTypeCode: $detail->scanTypeCode,
                    scanTime: $scanTime,
                    description: $detail->description,
                    ownerType: $order->owner_type,
                    ownerId: $order->owner_id,
                ),
                'order_id' => $order->id,
                'tracking_number' => $trackingNumber,
                'scan_type_code' => $detail->scanTypeCode,
                'scan_time' => $scanTime->format('Y-m-d H:i:s'),
                'order_reference' => $order->order_id,
                'scan_type_name' => $detail->scanTypeName,
                'scan_type' => $detail->scanType,
                'description' => $detail->description,
                'scan_network_type_name' => $detail->scanNetworkTypeName,
                'scan_network_name' => $detail->scanNetworkName,
                'scan_network_contact' => $detail->scanNetworkContact,
                'scan_network_province' => $detail->scanNetworkProvince,
                'scan_network_city' => $detail->scanNetworkCity,
                'scan_network_area' => $detail->scanNetworkArea,
                'scan_network_country' => $detail->scanNetworkCountry,
                'post_code' => $detail->postCode,
                'next_stop_name' => $detail->nextStopName,
                'next_network_province_name' => $detail->nextNetworkProvinceName,
                'next_network_city_name' => $detail->nextNetworkCityName,
                'next_network_area_name' => $detail->nextNetworkAreaName,
                'remark' => $detail->remark,
                'problem_type' => $detail->problemType,
                'payment_status' => $detail->paymentStatus,
                'payment_method' => $detail->paymentMethod,
                'actual_weight' => $detail->actualWeight,
                'longitude' => $detail->longitude,
                'latitude' => $detail->latitude,
                'time_zone' => $detail->timeZone,
                'scan_network_id' => $detail->scanNetworkId,
                'staff_name' => $detail->staffName,
                'staff_contact' => $detail->staffContact,
                'otp' => $detail->otp,
                'second_level_type_code' => $detail->secondLevelTypeCode,
                'wc_trace_flag' => $detail->wcTraceFlag,
                'signature_picture_url' => $detail->signaturePictureUrl,
                'sign_url' => $detail->signUrl,
                'electronic_signature_pic_url' => $detail->electronicSignaturePicUrl,
                'payload' => json_encode($detail->toApiArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'owner_type' => $order->owner_type,
                'owner_id' => $order->owner_id,
                'created_at' => CarbonImmutable::now()->format('Y-m-d H:i:s'),
                'updated_at' => CarbonImmutable::now()->format('Y-m-d H:i:s'),
            ];
        }

        if ($rows === []) {
            return;
        }

        app(StoreTrackingEvents::class)->insertMissing($rows);
    }

}
