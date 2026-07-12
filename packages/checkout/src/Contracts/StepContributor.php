<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Contracts;

use Closure;

interface StepContributor
{
    /** @return array<string, Closure(): CheckoutStepInterface> */
    public function steps(): array;
}
