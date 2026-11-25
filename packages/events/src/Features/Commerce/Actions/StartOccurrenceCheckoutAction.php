<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Contracts\EventCheckoutIntentResolver;

final class StartOccurrenceCheckoutAction
{
    public function __construct(
        private readonly EventCheckoutIntentResolver $checkoutIntentResolver,
    ) {}

    public function handle(string $scopeType, string $scopeId, string $registrationId): mixed
    {
        return $this->checkoutIntentResolver->resolve($scopeType, $scopeId, $registrationId);
    }
}
