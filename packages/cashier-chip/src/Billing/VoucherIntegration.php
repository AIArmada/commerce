<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Billing;

use AIArmada\Vouchers\Services\VoucherService;
use LogicException;

final class VoucherIntegration
{
    public static function service(): VoucherService
    {
        self::assertAvailable();

        /** @var VoucherService $service */
        $service = app(VoucherService::class);

        return $service;
    }

    public static function assertAvailable(): void
    {
        $enabled = config('cashier-chip.integrations.vouchers.enabled');

        if ($enabled === false || $enabled === 'false' || $enabled === 0 || $enabled === '0') {
            throw new LogicException(
                'Cashier CHIP coupon paths are disabled by cashier-chip.integrations.vouchers.enabled.',
            );
        }

        if (! class_exists(VoucherService::class)) {
            throw new LogicException(
                'Cashier CHIP coupon paths require the optional aiarmada/vouchers package to be installed.',
            );
        }
    }
}
