<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;

final class FulfillEventOrderItemAction
{
    public function __construct(
        private readonly EventOrderItemFulfillmentResolver $fulfillmentResolver,
    ) {}

    public function handle(string $registrationItemId): void
    {
        $this->fulfillmentResolver->resolve($registrationItemId);
    }
}
