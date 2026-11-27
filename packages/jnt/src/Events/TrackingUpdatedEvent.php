<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Events;

use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TrackingUpdatedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly TrackingData $tracking) {}

    public function getOrderId(): ?string
    {
        return $this->tracking->orderId;
    }

    public function getTrackingNumber(): string
    {
        return $this->tracking->trackingNumber;
    }

    public function getLatestStatus(): ?string
    {
        if (count($this->tracking->details) === 0) {
            return null;
        }

        $latest = $this->tracking->details[array_key_last($this->tracking->details)];

        return $latest instanceof TrackingDetailData ? $latest->scanType : null;
    }

    public function getLatestDescription(): ?string
    {
        if (count($this->tracking->details) === 0) {
            return null;
        }

        $latest = $this->tracking->details[array_key_last($this->tracking->details)];

        return $latest instanceof TrackingDetailData ? $latest->description : null;
    }

    public function getLatestLocation(): ?string
    {
        if (count($this->tracking->details) === 0) {
            return null;
        }

        $latest = $this->tracking->details[array_key_last($this->tracking->details)];

        if (! ($latest instanceof TrackingDetailData)) {
            return null;
        }

        $parts = array_filter([
            $latest->scanNetworkCity ?? null,
            $latest->scanNetworkProvince ?? null,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    public function isDelivered(): bool
    {
        return collect($this->tracking->details)->some(
            fn (TrackingDetailData $detail): bool => in_array($detail->scanType, ['DELIVER', 'SIGNED'], true)
        );
    }

    public function isInTransit(): bool
    {
        return collect($this->tracking->details)->some(
            fn (TrackingDetailData $detail): bool => in_array($detail->scanType, ['TRANSFER', 'ARRIVAL'], true)
        );
    }

    public function hasProblems(): bool
    {
        return collect($this->tracking->details)->some(
            fn (TrackingDetailData $detail): bool => in_array($detail->scanType, ['RETURN', 'REJECT', 'PROBLEM'], true)
        );
    }

    public function isCollected(): bool
    {
        return collect($this->tracking->details)->some(
            fn (TrackingDetailData $detail): bool => $detail->scanType === 'COLLECT'
        );
    }

    /**
     * @return array<int, TrackingDetailData>
     */
    public function getDetails(): array
    {
        return $this->tracking->details;
    }

    public function getDetailCount(): int
    {
        return count($this->tracking->details);
    }
}
