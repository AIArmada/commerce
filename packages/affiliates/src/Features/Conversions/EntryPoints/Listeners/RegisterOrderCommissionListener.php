<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\EntryPoints\Listeners;

use AIArmada\Affiliates\Features\Conversions\Api\OrderCommissionRegistration;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use Illuminate\Support\Facades\Event;

final class RegisterOrderCommissionListener implements OrderCommissionRegistration
{
    public function register(): void
    {
        Event::listen(CommissionAttributionRequired::class, RecordCommissionForOrder::class);
    }
}
