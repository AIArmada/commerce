<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

/**
 * Webhook payload received from J&T Express servers.
 *
 * J&T sends tracking updates via POST webhooks with structure:
 * {
 *     "digest": "base64_signature",
 *     "bizContent": "{\"billCode\":\"JT001\",\"details\":[...]}",
 *     "apiAccount": "640826271705595946",
 *     "timestamp": "1622520000000"
 * }
 */
final readonly class WebhookData
{
    /**
     * @param  list<TrackingDetailData>  $details  Array of tracking update details
     */
    public function __construct(
        public string $billCode,
        public ?string $txlogisticId,

        public array $details,
    ) {}

    /**
     * Get the latest tracking update.
     */
    public function getLatestDetail(): ?TrackingDetailData
    {
        return TrackingDetailData::latest($this->details);
    }

    public function getLatestStatus(): ?string
    {
        return $this->getLatestDetail()?->scanType;
    }

    public function getLatestLocation(): ?string
    {
        return $this->getLatestDetail()?->scanNetworkName;
    }

    public function isDelivered(): bool
    {
        $latest = $this->getLatestDetail();

        return $latest !== null && $latest->isDelivered();
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $latest = $this->getLatestDetail();

        return [
            'billCode' => $this->billCode,
            'txlogisticId' => $this->txlogisticId,
            'details' => array_map(fn (TrackingDetailData $detail): array => [
                'scanType' => $detail->scanType,
                'scanNetworkName' => $detail->scanNetworkName,
                'description' => $detail->description,
                'scanTime' => $detail->scanTime,
            ], $this->details),
            'latestStatus' => $latest?->scanType,
            'latestLocation' => $latest?->scanNetworkName,
            'latestTime' => $latest?->scanTime,
        ];
    }
}
