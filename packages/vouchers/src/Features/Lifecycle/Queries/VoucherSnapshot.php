<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Queries;

use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherStatus;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Features\Lifecycle\Models\Voucher;
use BackedEnum;

final class VoucherSnapshot
{
    public static function fromModel(Voucher $voucher): VoucherData
    {
        $type = $voucher->type;

        if (! $type instanceof VoucherType) {
            $type = VoucherType::from($type);
        }

        $status = VoucherStatus::from($voucher->status->getValue());

        return new VoucherData(
            id: $voucher->id,
            code: $voucher->code,
            name: $voucher->name,
            description: $voucher->description,
            type: $type,
            value: (int) $voucher->value,
            valueConfig: $voucher->value_config,
            creditDestination: $voucher->credit_destination,
            creditDelayHours: (int) ($voucher->credit_delay_hours ?? 0),
            currency: $voucher->currency,
            minCartValue: $voucher->min_cart_value !== null ? (int) $voucher->min_cart_value : null,
            maxDiscount: $voucher->max_discount !== null ? (int) $voucher->max_discount : null,
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
            promotionId: $voucher->promotion_id,
            affiliateCommissionType: self::normalizeAffiliateCommissionType($voucher->affiliate_commission_type),
            affiliateCommissionValue: $voucher->affiliate_commission_value,
            affiliateId: $voucher->affiliate_id,
            affiliateProgramId: $voucher->affiliate_program_id,
            affiliateUplineLevels: $voucher->affiliate_upline_levels,
        );
    }

    private static function normalizeAffiliateCommissionType(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
