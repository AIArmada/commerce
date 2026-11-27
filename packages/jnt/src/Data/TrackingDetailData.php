<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use AIArmada\Jnt\Exceptions\JntValidationException;

/**
 * Tracking detail data from JNT Express API.
 */


final readonly class TrackingDetailData
{
    public function __construct(
        public string $scanTime,
        public string $description,
        public string $scanTypeCode,
        public string $scanTypeName,
        public string $scanType,
        public ?string $actualWeight = null,
        public ?string $scanNetworkTypeName = null,
        public ?string $scanNetworkName = null,
        public ?string $staffName = null,
        public ?string $staffContact = null,
        public ?string $scanNetworkContact = null,
        public ?string $scanNetworkProvince = null,
        public ?string $scanNetworkCity = null,
        public ?string $scanNetworkArea = null,
        public ?string $signaturePictureUrl = null,
        public ?string $longitude = null,
        public ?string $latitude = null,
        public ?string $timeZone = null,
        // Additional fields from complete API documentation
        public ?string $otp = null,
        public ?string $secondLevelTypeCode = null,
        public ?string $wcTraceFlag = null,
        public ?string $postCode = null,
        public ?string $paymentStatus = null,
        public ?string $paymentMethod = null,
        public ?string $nextStopName = null,
        public ?string $remark = null,
        public ?string $nextNetworkProvinceName = null,
        public ?string $nextNetworkCityName = null,
        public ?string $nextNetworkAreaName = null,
        public ?string $problemType = null,
        public ?string $signUrl = null,
        public ?string $electronicSignaturePicUrl = null,
        public ?int $scanNetworkId = null,
        public ?string $scanNetworkCountry = null,
    ) {}

    /**
     * Create from JNT API response array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromApiArray(array $data): self
    {
        foreach (['scanTime', 'desc', 'scanTypeCode', 'scanTypeName', 'scanType'] as $key) {
            if (! isset($data[$key]) || ! is_scalar($data[$key])) {
                throw JntValidationException::requiredFieldMissing($key);
            }
        }

        return new self(
            scanTime: (string) $data['scanTime'],
            description: (string) $data['desc'],
            scanTypeCode: (string) $data['scanTypeCode'],
            scanTypeName: (string) $data['scanTypeName'],
            scanType: (string) $data['scanType'],
            actualWeight: $data['realWeight'] ?? null,
            scanNetworkTypeName: $data['scanNetworkTypeName'] ?? null,
            scanNetworkName: $data['scanNetworkName'] ?? null,
            staffName: $data['staffName'] ?? null,
            staffContact: $data['staffContact'] ?? null,
            scanNetworkContact: $data['scanNetworkContact'] ?? null,
            scanNetworkProvince: $data['scanNetworkProvince'] ?? null,
            scanNetworkCity: $data['scanNetworkCity'] ?? null,
            scanNetworkArea: $data['scanNetworkArea'] ?? null,
            signaturePictureUrl: $data['sigPicUrl'] ?? null,
            longitude: $data['longitude'] ?? null,
            latitude: $data['latitude'] ?? null,
            timeZone: $data['timeZone'] ?? null,
            otp: $data['otp'] ?? null,
            secondLevelTypeCode: $data['secondLevelTypeCode'] ?? null,
            wcTraceFlag: $data['wcTraceFlag'] ?? null,
            postCode: $data['postCode'] ?? null,
            paymentStatus: $data['paymentStatus'] ?? null,
            paymentMethod: $data['paymentMethod'] ?? null,
            nextStopName: $data['nextStopName'] ?? null,
            remark: $data['remark'] ?? null,
            nextNetworkProvinceName: $data['nextNetworkProvinceName'] ?? null,
            nextNetworkCityName: $data['nextNetworkCityName'] ?? null,
            nextNetworkAreaName: $data['nextNetworkAreaName'] ?? null,
            problemType: $data['problemType'] ?? null,
            signUrl: $data['signUrl'] ?? null,
            electronicSignaturePicUrl: $data['electronicSignaturePicUrl'] ?? null,
            scanNetworkId: isset($data['scanNetworkId']) ? (int) $data['scanNetworkId'] : null,
            scanNetworkCountry: $data['scanNetworkCountray'] ?? null, // API has typo
        );
    }

    /**
     * Convert to JNT API request array.
     *
     * @return array<string, string|int>
     */
    public function toApiArray(): array
    {
        return array_filter([
            'scanTime' => $this->scanTime,
            'desc' => $this->description,
            'scanTypeCode' => $this->scanTypeCode,
            'scanTypeName' => $this->scanTypeName,
            'scanType' => $this->scanType,
            'realWeight' => $this->actualWeight,
            'scanNetworkTypeName' => $this->scanNetworkTypeName,
            'scanNetworkName' => $this->scanNetworkName,
            'staffName' => $this->staffName,
            'staffContact' => $this->staffContact,
            'scanNetworkContact' => $this->scanNetworkContact,
            'scanNetworkProvince' => $this->scanNetworkProvince,
            'scanNetworkCity' => $this->scanNetworkCity,
            'scanNetworkArea' => $this->scanNetworkArea,
            'sigPicUrl' => $this->signaturePictureUrl,
            'longitude' => $this->longitude,
            'latitude' => $this->latitude,
            'timeZone' => $this->timeZone,
            'otp' => $this->otp,
            'secondLevelTypeCode' => $this->secondLevelTypeCode,
            'wcTraceFlag' => $this->wcTraceFlag,
            'postCode' => $this->postCode,
            'paymentStatus' => $this->paymentStatus,
            'paymentMethod' => $this->paymentMethod,
            'nextStopName' => $this->nextStopName,
            'remark' => $this->remark,
            'nextNetworkProvinceName' => $this->nextNetworkProvinceName,
            'nextNetworkCityName' => $this->nextNetworkCityName,
            'nextNetworkAreaName' => $this->nextNetworkAreaName,
            'problemType' => $this->problemType,
            'signUrl' => $this->signUrl,
            'electronicSignaturePicUrl' => $this->electronicSignaturePicUrl,
            'scanNetworkId' => $this->scanNetworkId,
            'scanNetworkCountray' => $this->scanNetworkCountry, // API has typo
        ], fn (string | int | null $value): bool => $value !== null);
    }

    public function getLocation(): string
    {
        return implode(', ', array_filter([
            $this->scanNetworkArea,
            $this->scanNetworkCity,
            $this->scanNetworkProvince,
        ])) ?: $this->scanNetworkName ?? '';
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function isDelivered(): bool
    {
        return in_array($this->scanType, ['SIGN', 'SIGN_STATION'], true);
    }

    public function isInTransit(): bool
    {
        return in_array($this->scanType, ['TMS_SIGN', 'ARRIVE', 'DEPARTURE', 'LOAD'], true);
    }
}
