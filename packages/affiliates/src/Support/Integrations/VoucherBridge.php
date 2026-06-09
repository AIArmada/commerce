<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Integrations;

use AIArmada\CommerceSupport\Support\OwnerContext;

final class VoucherBridge
{
    private bool $available;

    public function __construct()
    {
        $this->available = class_exists('AIArmada\\FilamentVouchers\\Models\\Voucher') && class_exists('AIArmada\\FilamentVouchers\\Resources\\VoucherResource');
    }

    public function warm(): void {}

    public function isAvailable(): bool
    {
        return $this->available && (bool) config('filament-affiliates.integrations.filament_vouchers', true);
    }

    public function resolveUrl(?string $code): ?string
    {
        if (! $this->isAvailable() || ! $code) {
            return null;
        }

        $voucherResourceClass = 'AIArmada\\FilamentVouchers\\Resources\\VoucherResource';

        $voucherQuery = $voucherResourceClass::getEloquentQuery()->where('code', $code);

        if ((bool) config('vouchers.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            $voucherQuery->forOwner($owner, false);
        }

        $voucher = $voucherQuery->first();

        if (! $voucher) {
            return null;
        }

        return $voucherResourceClass::getUrl('view', ['record' => $voucher]);
    }
}
