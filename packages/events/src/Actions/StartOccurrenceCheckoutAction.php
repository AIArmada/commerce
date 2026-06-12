<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\EventCheckoutIntentResolver;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use Lorisleiva\Actions\Concerns\AsAction;

final class StartOccurrenceCheckoutAction
{
    use AsAction;

    public function __construct(
        private readonly EventCheckoutIntentResolver $checkoutIntentResolver,
    ) {}

    public function handle(EventOccurrence $occurrence, EventRegistration $registration): mixed
    {
        return $this->checkoutIntentResolver->resolve($occurrence, $registration);
    }
}
