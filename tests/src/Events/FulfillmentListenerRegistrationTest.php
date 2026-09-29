<?php

declare(strict_types=1);

use AIArmada\Events\Listeners\SyncEventOrderRegistrationsOnFulfillment;
use AIArmada\Orders\Events\OrderFulfillmentRequired;
use Illuminate\Support\Facades\Event;

it('registers registration sync on the fulfillment event', function (): void {
    // EventsServiceProvider is auto-loaded by the suite TestCase.
    expect(Event::getRawListeners()[OrderFulfillmentRequired::class] ?? [])
        ->toContain(SyncEventOrderRegistrationsOnFulfillment::class);
});
