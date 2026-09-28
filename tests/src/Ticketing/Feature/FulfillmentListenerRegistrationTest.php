<?php

declare(strict_types=1);

use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Ticketing\Listeners\IssuePassesOnFulfillment;
use Illuminate\Support\Facades\Event;

it('registers pass issuance on the fulfillment event', function (): void {
    // TicketingServiceProvider is auto-loaded by the suite TestCase.
    expect(Event::getRawListeners()[OrderFulfillmentRequired::class] ?? [])
        ->toContain(IssuePassesOnFulfillment::class);
});
