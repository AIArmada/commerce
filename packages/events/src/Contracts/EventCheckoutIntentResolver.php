<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventCheckoutIntentData;
use AIArmada\Events\Models\Occurrence;

interface EventCheckoutIntentResolver
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function resolve(Occurrence $occurrence, int $quantity = 1, array $metadata = []): ?EventCheckoutIntentData;
}
