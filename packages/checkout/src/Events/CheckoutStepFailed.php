<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Events;

use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CheckoutStepFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, string>  $errors
     */
    public function __construct(
        public readonly CheckoutSession $session,
        public readonly string $stepIdentifier,
        public readonly array $errors = [],
    ) {}
}
