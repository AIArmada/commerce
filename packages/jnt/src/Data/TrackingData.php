<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use InvalidArgumentException;

/**
 * Tracking data from JNT Express API.
 */
final readonly class TrackingData
{
    /**
     * @param  list<TrackingDetailData>  $details
     */
    public function __construct(
        public string $trackingNumber,

        public array $details,
        public ?string $orderId = null,
    ) {}

    /**
     * Create from array of TrackingDetailData objects.
     *
     * @param  array<int, TrackingDetailData>  $details
     */
    public static function make(string $trackingNumber, array $details, ?string $orderId = null): self
    {
        return new self(
            trackingNumber: $trackingNumber,
            details: array_values($details),
            orderId: $orderId,
        );
    }

    /**
     * Create from JNT API response array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromApiArray(array $data): self
    {
        if (! isset($data['billCode']) || ! is_string($data['billCode']) || $data['billCode'] === '') {
            throw new InvalidArgumentException('Missing or invalid billCode.');
        }

        if (isset($data['txlogisticId']) && ! is_string($data['txlogisticId'])) {
            throw new InvalidArgumentException('Invalid txlogisticId: expected a string.');
        }

        $rawDetails = $data['details'] ?? [];

        if (! is_array($rawDetails)) {
            throw new InvalidArgumentException('Invalid details: expected an array.');
        }

        $details = array_map(
            static function (mixed $detail): TrackingDetailData {
                if (! is_array($detail)) {
                    throw new InvalidArgumentException('Invalid tracking detail: expected an array.');
                }

                return TrackingDetailData::fromApiArray($detail);
            },
            array_is_list($rawDetails) ? $rawDetails : [$rawDetails],
        );

        return self::make(
            trackingNumber: $data['billCode'],
            details: $details,
            orderId: $data['txlogisticId'] ?? null,
        );
    }

    /**
     * Convert to JNT API request array.
     *
     * @return array{billCode: string, txlogisticId: string|null, details: array<int, array<string, mixed>>}
     */
    public function toApiArray(): array
    {
        return [
            'billCode' => $this->trackingNumber,
            'txlogisticId' => $this->orderId,
            'details' => array_map(static fn (TrackingDetailData $detail): array => $detail->toApiArray(), $this->details),
        ];
    }

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

        return $latest !== null && in_array($latest->scanType, ['SIGN', 'SIGN_STATION'], true);
    }
}
