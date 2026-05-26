<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Models;

use AIArmada\FilamentVouchers\Support\OwnerTypeRegistry;
use AIArmada\Vouchers\Models\Voucher as BaseVoucher;

/**
 * Extended Voucher model with Filament-specific attributes.
 */
final class Voucher extends BaseVoucher
{
    public function getOwnerDisplayNameAttribute(): ?string
    {
        $owner = $this->owner;

        if (! $owner) {
            return null;
        }

        return app(OwnerTypeRegistry::class)->resolveDisplayLabel($owner);
    }

    public function getPromotionSourceIdAttribute(): ?string
    {
        $promotion = $this->promotion;

        if ($promotion !== null && $promotion->getKey() !== null) {
            return (string) $promotion->getKey();
        }

        return $this->normalizePromotionSourceString(
            data_get($this->metadata, 'source_promotion_id', $this->promotion_id)
        );
    }

    public function getPromotionSourceNameAttribute(): ?string
    {
        $promotion = $this->promotion;

        if ($promotion !== null) {
            return $this->normalizePromotionSourceString($promotion->getAttribute('name'));
        }

        return $this->normalizePromotionSourceString(data_get($this->metadata, 'source_promotion_name'));
    }

    public function getPromotionSourceCodeAttribute(): ?string
    {
        $promotion = $this->promotion;

        if ($promotion !== null) {
            return $this->normalizePromotionSourceString($promotion->getAttribute('code'));
        }

        return $this->normalizePromotionSourceString(data_get($this->metadata, 'source_promotion_code'));
    }

    public function getPromotionSourceLabelAttribute(): ?string
    {
        $name = $this->promotion_source_name;
        $code = $this->promotion_source_code;

        if ($name !== null && $code !== null) {
            return $name . ' (' . $code . ')';
        }

        return $name ?? $code;
    }

    private function normalizePromotionSourceString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = mb_trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
