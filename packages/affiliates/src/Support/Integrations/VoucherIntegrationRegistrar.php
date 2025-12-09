<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Integrations;

use AIArmada\Affiliates\Listeners\AttachAffiliateFromVoucher;
use AIArmada\Vouchers\Events\VoucherApplied;
use Illuminate\Contracts\Events\Dispatcher;

final class VoucherIntegrationRegistrar
{
    public function __construct(private readonly Dispatcher $events) {}

    public function register(): void
    {
        if (! class_exists(VoucherApplied::class)) {
            return;
        }

        if (! config('affiliates.integrations.vouchers.attach_on_apply', true)) {
            return;
        }

        $this->events->listen(
            VoucherApplied::class,
            AttachAffiliateFromVoucher::class
        );
    }
}
