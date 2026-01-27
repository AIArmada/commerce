<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Events;

use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CheckoutFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CheckoutSession $session,
        public readonly string $reason,
    ) {}
}
