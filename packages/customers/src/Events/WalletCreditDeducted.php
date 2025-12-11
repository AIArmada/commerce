<?php

declare(strict_types=1);

namespace AIArmada\Customers\Events;

use AIArmada\Customers\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletCreditDeducted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Customer $customer,
        public int $amountInCents,
        public ?string $reason = null
    ) {}
}
