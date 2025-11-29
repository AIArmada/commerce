<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Integrations;

use AIArmada\Affiliates\Listeners\AttachAffiliateFromVoucher;
use Illuminate\Contracts\Events\Dispatcher;

final class VoucherIntegrationRegistrar
{
    public function __construct(private readonly Dispatcher $events) {}

    public function register(): void
    {
        if (! class_exists(\AIArmada\Vouchers\Events\VoucherApplied::class)) {
            return;
        }

        if (! config('affiliates.integrations.vouchers.attach_on_apply', true)) {
            return;
        }

        $this->events->listen(
            \AIArmada\Vouchers\Events\VoucherApplied::class,
            AttachAffiliateFromVoucher::class
        );
    }
}
