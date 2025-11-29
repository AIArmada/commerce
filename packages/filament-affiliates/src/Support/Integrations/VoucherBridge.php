<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Support\Integrations;

use AIArmada\FilamentVouchers\Models\Voucher;
use AIArmada\FilamentVouchers\Resources\VoucherResource;

final class VoucherBridge
{
    private bool $available;

    public function __construct()
    {
        $this->available = class_exists(Voucher::class) && class_exists(VoucherResource::class);
    }

    public function warm(): void
    {
        // placeholder for runtime hooks
    }

    public function isAvailable(): bool
    {
        return $this->available && (bool) config('filament-affiliates.integrations.filament_vouchers', true);
    }

    public function resolveUrl(?string $code): ?string
    {
        if (! $this->isAvailable() || ! $code) {
            return null;
        }

        $voucher = Voucher::query()
            ->where('code', $code)
            ->first();

        if (! $voucher) {
            return null;
        }

        return VoucherResource::getUrl('view', ['record' => $voucher]);
    }
}
