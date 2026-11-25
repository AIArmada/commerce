<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Listeners;

use AIArmada\CommerceSupport\Events\PaymentRefunded;
use AIArmada\Events\Features\Commerce\Actions\CompletePendingRefundOnPaymentRefundedAction;

final class CompletePendingRefundOnPaymentRefunded
{
    public function __construct(
        private readonly CompletePendingRefundOnPaymentRefundedAction $completeRefund,
    ) {}

    public function handle(PaymentRefunded $event): void
    {
        $this->completeRefund->handle($event);
    }
}
