<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use AIArmada\Jnt\Exceptions\JntValidationException;

/**
 * Order data from JNT Express API.
 */
final readonly class OrderData
{
    /**
     * @param  array<string>|null  $additionalTrackingNumbers
     */
    public function __construct(
        public string $orderId,
        public ?string $trackingNumber = null,
        public ?string $sortingCode = null,
        public ?string $thirdSortingCode = null,
        public ?array $additionalTrackingNumbers = null,
        public ?string $chargeableWeight = null,
    ) {}

    /**
     * Create from JNT API response array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromApiArray(array $data): self
    {
        if (! isset($data['txlogisticId']) || ! is_string($data['txlogisticId']) || $data['txlogisticId'] === '') {
            throw JntValidationException::requiredFieldMissing('txlogisticId');
        }

        return new self(
            orderId: $data['txlogisticId'],
            trackingNumber: $data['billCode'] ?? null,
            sortingCode: $data['sortingCode'] ?? null,
            thirdSortingCode: $data['thirdSortingCode'] ?? null,
            additionalTrackingNumbers: $data['multipleVoteBillCodes'] ?? null,
            chargeableWeight: $data['packageChargeWeight'] ?? null,
        );
    }

    /**
     * Convert to JNT API request array.
     *
     * @return array<string, string|array<string>>
     */
    public function toApiArray(): array
    {
        return array_filter([
            'txlogisticId' => $this->orderId,
            'billCode' => $this->trackingNumber,
            'sortingCode' => $this->sortingCode,
            'thirdSortingCode' => $this->thirdSortingCode,
            'multipleVoteBillCodes' => $this->additionalTrackingNumbers,
            'packageChargeWeight' => $this->chargeableWeight,
        ], fn (string | array | null $value): bool => $value !== null);
    }

    public function hasTrackingNumber(): bool
    {
        return $this->trackingNumber !== null;
    }

    public function hasMultipleTrackingNumbers(): bool
    {
        return $this->additionalTrackingNumbers !== null && count($this->additionalTrackingNumbers) > 0;
    }

    /**
     * Get all tracking numbers including the primary one.
     *
     * @return array<string>
     */
    public function getAllTrackingNumbers(): array
    {
        $numbers = [];

        if ($this->trackingNumber !== null) {
            $numbers[] = $this->trackingNumber;
        }

        if ($this->additionalTrackingNumbers !== null) {
            $numbers = array_merge($numbers, $this->additionalTrackingNumbers);
        }

        return $numbers;
    }
}
