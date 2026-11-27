<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Actions;

use AIArmada\Jnt\Contracts\TrackingReader;
use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Data\TrackingStatus;
use AIArmada\Jnt\Features\Tracking\Api\ReadTrackingStatus;
use AIArmada\Jnt\Features\Tracking\Support\JntStatusMapper;
use AIArmada\Jnt\Services\JntExpressService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

class JntTrackingService implements ReadTrackingStatus, TrackingReader
{
    public function __construct(
        private readonly JntExpressService $expressService,
        private readonly JntStatusMapper $statusMapper,
    ) {}

    /**
     * Get the normalized tracking status for a tracking detail
     */
    public function getNormalizedStatus(TrackingDetailData $detail): TrackingStatus
    {
        return $this->statusMapper->resolve(
            scanTypeCode: $detail->scanTypeCode,
            statusDescription: $detail->description !== ''
                ? $detail->description
                : ($detail->scanTypeName !== '' ? $detail->scanTypeName : $detail->scanType),
        );
    }

    /**
     * Get the current normalized status from tracking data
     */
    public function getCurrentStatus(TrackingData $trackingData): TrackingStatus
    {
        if (count($trackingData->details) === 0) {
            return TrackingStatus::Pending;
        }

        $latestDetail = $trackingData->getLatestDetail();

        if ($latestDetail === null) {
            return TrackingStatus::Pending;
        }

        return $this->getNormalizedStatus($latestDetail);
    }

    /**
     * Track a parcel and return normalized status information
     *
     * @return array{tracking_number: string, order_id: string|null, current_status: TrackingStatus, events: array<array{status: TrackingStatus, description: string, location: string|null, occurred_at: CarbonImmutable, raw: TrackingDetailData}>}
     */
    public function track(?string $orderId = null, ?string $trackingNumber = null): array
    {
        $trackingData = $this->expressService->trackParcel($orderId, $trackingNumber);

        return $this->parseTrackingData($trackingData);
    }

    /**
     * Parse tracking data into normalized format
     *
     * @return array{tracking_number: string, order_id: string|null, current_status: TrackingStatus, events: array<array{status: TrackingStatus, description: string, location: string|null, occurred_at: CarbonImmutable, raw: TrackingDetailData}>}
     */
    public function parseTrackingData(TrackingData $trackingData): array
    {
        $events = [];

        foreach ($trackingData->details as $detail) {
            $occurredAt = $this->parseDetailScanTime($detail);

            if ($occurredAt === null) {
                continue;
            }

            $events[] = [
                'status' => $this->getNormalizedStatus($detail),
                'description' => $detail->description,
                'location' => $this->formatLocation($detail),
                'occurred_at' => $occurredAt,
                'raw' => $detail,
            ];
        }

        return [
            'tracking_number' => $trackingData->trackingNumber,
            'order_id' => $trackingData->orderId,
            'current_status' => $this->getCurrentStatus($trackingData),
            'events' => $events,
        ];
    }

    public function parseDetailScanTime(TrackingDetailData $detail): ?CarbonImmutable
    {
        if (mb_trim($detail->scanTime) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($detail->scanTime);
        } catch (Throwable) {
            Log::channel(config('jnt.logging.channel', 'stack'))
                ->warning('J&T tracking ignoring unparseable scanTime', [
                    'scan_type_code' => $detail->scanTypeCode,
                ]);

            return null;
        }
    }

    /**
     * Format location from tracking detail
     */
    private function formatLocation(TrackingDetailData $detail): ?string
    {
        $parts = array_filter([
            $detail->scanNetworkArea,
            $detail->scanNetworkCity,
            $detail->scanNetworkProvince,
            $detail->scanNetworkCountry,
        ]);

        if (empty($parts)) {
            return $detail->scanNetworkName;
        }

        return implode(', ', $parts);
    }
}
