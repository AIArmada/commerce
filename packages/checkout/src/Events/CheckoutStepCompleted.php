<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Events;

use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CheckoutStepCompleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly CheckoutSession $session,
        public readonly string $stepIdentifier,
        public readonly array $data = [],
    ) {}
}
