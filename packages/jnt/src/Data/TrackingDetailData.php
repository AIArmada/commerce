<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

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
                throw new InvalidArgumentException("Missing or invalid {$key}.");
            }
        }

        return new self(
            scanTime: (string) $data['scanTime'],
            description: (string) $data['desc'],
            scanTypeCode: (string) $data['scanTypeCode'],
            scanTypeName: (string) $data['scanTypeName'],
            scanType: (string) $data['scanType'],
            actualWeight: self::optionalString($data, 'realWeight'),
            scanNetworkTypeName: self::optionalString($data, 'scanNetworkTypeName'),
            scanNetworkName: self::optionalString($data, 'scanNetworkName'),
            staffName: self::optionalString($data, 'staffName'),
            staffContact: self::optionalString($data, 'staffContact'),
            scanNetworkContact: self::optionalString($data, 'scanNetworkContact'),
            scanNetworkProvince: self::optionalString($data, 'scanNetworkProvince'),
            scanNetworkCity: self::optionalString($data, 'scanNetworkCity'),
            scanNetworkArea: self::optionalString($data, 'scanNetworkArea'),
            signaturePictureUrl: self::optionalString($data, 'sigPicUrl'),
            longitude: self::optionalString($data, 'longitude'),
            latitude: self::optionalString($data, 'latitude'),
            timeZone: self::optionalString($data, 'timeZone'),
            otp: self::optionalString($data, 'otp'),
            secondLevelTypeCode: self::optionalString($data, 'secondLevelTypeCode'),
            wcTraceFlag: self::optionalString($data, 'wcTraceFlag'),
            postCode: self::optionalString($data, 'postCode'),
            paymentStatus: self::optionalString($data, 'paymentStatus'),
            paymentMethod: self::optionalString($data, 'paymentMethod'),
            nextStopName: self::optionalString($data, 'nextStopName'),
            remark: self::optionalString($data, 'remark'),
            nextNetworkProvinceName: self::optionalString($data, 'nextNetworkProvinceName'),
            nextNetworkCityName: self::optionalString($data, 'nextNetworkCityName'),
            nextNetworkAreaName: self::optionalString($data, 'nextNetworkAreaName'),
            problemType: self::optionalString($data, 'problemType'),
            signUrl: self::optionalString($data, 'signUrl'),
            electronicSignaturePicUrl: self::optionalString($data, 'electronicSignaturePicUrl'),
            scanNetworkId: self::optionalNetworkId($data),
            scanNetworkCountry: self::optionalString($data, 'scanNetworkCountray'), // API has typo
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

    /** @param array<string, mixed> $data */
    private static function optionalString(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException("Invalid {$field}: expected a scalar string or number.");
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $data */
    private static function optionalNetworkId(array $data): ?int
    {
        $value = $data['scanNetworkId'] ?? null;

        if ($value === null) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);

        if ($id === false || is_bool($value) || ! is_scalar($value)) {
            throw new InvalidArgumentException('Invalid scanNetworkId: expected an integer.');
        }

        return $id;
    }

    /** @param list<self> $details */
    public static function latest(array $details): ?self
    {
        $latest = null;
        $latestTime = null;

        foreach ($details as $detail) {
            if (mb_trim($detail->scanTime) === '') {
                continue;
            }

            try {
                $time = CarbonImmutable::parse($detail->scanTime);
            } catch (Throwable) {
                continue;
            }

            if ($latestTime === null || $time > $latestTime) {
                $latest = $detail;
                $latestTime = $time;
            }
        }

        return $latest;
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
