<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Data;

use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use Carbon\CarbonImmutable;
use DateTimeInterface;

readonly class VoucherData
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public ?string $description,
        public VoucherType $type,
        public float $value,
        /** @var ?array<string, mixed> Configuration for compound voucher types */
        public ?array $valueConfig,
        public ?string $creditDestination,
        public int $creditDelayHours,
        public string $currency,
        public ?float $minCartValue,
        public ?float $maxDiscount,
        public ?int $usageLimit,
        public ?int $usageLimitPerUser,
        public bool $allowsManualRedemption,
        public int|string|null $ownerId,
        public ?string $ownerType,
        public ?DateTimeInterface $startsAt,
        public ?DateTimeInterface $expiresAt,
        public VoucherStatus $status,
        /** @var ?array<string, mixed> */
        public ?array $targetDefinition,
        /** @var ?array<string, mixed> */
        public ?array $metadata,
    ) {}

    public static function fromModel(\AIArmada\Vouchers\Models\Voucher $voucher): self
    {
        $type = $voucher->type;

        if (! $type instanceof VoucherType) {
            $type = VoucherType::from($type);
        }

        $status = $voucher->status;

        if (! $status instanceof VoucherStatus) {
            $status = VoucherStatus::from($status);
        }

        return new self(
            id: $voucher->id,
            code: $voucher->code,
            name: $voucher->name,
            description: $voucher->description,
            type: $type,
            value: (float) $voucher->value,
            valueConfig: $voucher->value_config,
            creditDestination: $voucher->credit_destination,
            creditDelayHours: (int) ($voucher->credit_delay_hours ?? 0),
            currency: $voucher->currency,
            minCartValue: $voucher->min_cart_value ? (float) $voucher->min_cart_value : null,
            maxDiscount: $voucher->max_discount ? (float) $voucher->max_discount : null,
            usageLimit: $voucher->usage_limit,
            usageLimitPerUser: $voucher->usage_limit_per_user,
            allowsManualRedemption: (bool) $voucher->allows_manual_redemption,
            ownerId: $voucher->owner_id,
            ownerType: $voucher->owner_type,
            startsAt: $voucher->starts_at,
            expiresAt: $voucher->expires_at,
            status: $status,
            targetDefinition: $voucher->target_definition,
            metadata: $voucher->metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $startsAt = isset($data['starts_at']) && is_string($data['starts_at'])
            ? CarbonImmutable::parse($data['starts_at'])
            : null;

        $expiresAt = isset($data['expires_at']) && is_string($data['expires_at'])
            ? CarbonImmutable::parse($data['expires_at'])
            : null;

        /** @var string|int $typeValue */
        $typeValue = $data['type'] ?? VoucherType::Fixed->value;
        /** @var string|int $statusValue */
        $statusValue = $data['status'] ?? VoucherStatus::Active->value;

        /** @var int|string|null $ownerId */
        $ownerId = $data['owner_id'] ?? null;

        /** @var array<string, mixed>|null $targetDefinition */
        $targetDefinition = isset($data['target_definition']) && is_array($data['target_definition'])
            ? $data['target_definition']
            : null;

        /** @var array<string, mixed>|null $metadata */
        $metadata = isset($data['metadata']) && is_array($data['metadata'])
            ? $data['metadata']
            : null;

        /** @var array<string, mixed>|null $valueConfig */
        $valueConfig = isset($data['value_config']) && is_array($data['value_config'])
            ? $data['value_config']
            : null;

        /** @var scalar|null $id */
        $id = $data['id'] ?? '';
        /** @var scalar|null $code */
        $code = $data['code'] ?? '';
        /** @var scalar|null $name */
        $name = $data['name'] ?? '';
        /** @var scalar|null $description */
        $description = $data['description'] ?? null;
        /** @var scalar|null $value */
        $value = $data['value'] ?? 0.0;
        /** @var scalar|null $currency */
        $currency = $data['currency'] ?? 'MYR';
        /** @var scalar|null $minCartValue */
        $minCartValue = $data['min_cart_value'] ?? null;
        /** @var scalar|null $maxDiscount */
        $maxDiscount = $data['max_discount'] ?? null;
        /** @var scalar|null $usageLimit */
        $usageLimit = $data['usage_limit'] ?? null;
        /** @var scalar|null $usageLimitPerUser */
        $usageLimitPerUser = $data['usage_limit_per_user'] ?? null;
        /** @var scalar|null $ownerType */
        $ownerType = $data['owner_type'] ?? null;
        /** @var scalar|null $creditDestination */
        $creditDestination = $data['credit_destination'] ?? null;
        /** @var scalar|null $creditDelayHours */
        $creditDelayHours = $data['credit_delay_hours'] ?? 0;

        return new self(
            id: (string) $id,
            code: (string) $code,
            name: (string) $name,
            description: $description !== null ? (string) $description : null,
            type: VoucherType::from($typeValue),
            value: (float) $value,
            valueConfig: $valueConfig,
            creditDestination: $creditDestination !== null ? (string) $creditDestination : null,
            creditDelayHours: (int) $creditDelayHours,
            currency: (string) $currency,
            minCartValue: $minCartValue !== null ? (float) $minCartValue : null,
            maxDiscount: $maxDiscount !== null ? (float) $maxDiscount : null,
            usageLimit: $usageLimit !== null ? (int) $usageLimit : null,
            usageLimitPerUser: $usageLimitPerUser !== null ? (int) $usageLimitPerUser : null,
            allowsManualRedemption: isset($data['allows_manual_redemption']) && (bool) $data['allows_manual_redemption'],
            ownerId: $ownerId,
            ownerType: $ownerType !== null ? (string) $ownerType : null,
            startsAt: $startsAt,
            expiresAt: $expiresAt,
            status: VoucherStatus::from($statusValue),
            targetDefinition: $targetDefinition,
            metadata: $metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'value' => $this->value,
            'value_config' => $this->valueConfig,
            'currency' => $this->currency,
            'min_cart_value' => $this->minCartValue,
            'max_discount' => $this->maxDiscount,
            'usage_limit' => $this->usageLimit,
            'usage_limit_per_user' => $this->usageLimitPerUser,
            'allows_manual_redemption' => $this->allowsManualRedemption,
            'owner_id' => $this->ownerId,
            'owner_type' => $this->ownerType,
            'starts_at' => $this->startsAt?->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'status' => $this->status->value,
            'target_definition' => $this->targetDefinition,
            'metadata' => $this->metadata,
            'credit_destination' => $this->creditDestination,
            'credit_delay_hours' => $this->creditDelayHours,
        ];
    }
}
