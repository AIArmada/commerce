<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Models\EventRegistrationItem;

final class FulfillEventOrderItemAction
{
    public function __construct(
        private readonly EventOrderItemFulfillmentResolver $fulfillmentResolver,
    ) {}

    public function handle(EventRegistrationItem $registrationItem): void
    {
        $this->fulfillmentResolver->resolve($registrationItem);
    }
}
