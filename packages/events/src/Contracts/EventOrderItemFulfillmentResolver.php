<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventOrderItemFulfillmentResolver
{
    public function resolve(string $registrationItemId): mixed;
}
