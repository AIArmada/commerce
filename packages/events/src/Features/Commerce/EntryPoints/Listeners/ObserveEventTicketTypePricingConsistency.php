<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Listeners;

use AIArmada\Events\Features\Commerce\Actions\AssertEventTicketTypePricingConsistencyAction;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;

final class ObserveEventTicketTypePricingConsistency
{
    public function __construct(
        private readonly AssertEventTicketTypePricingConsistencyAction $assertConsistency,
    ) {}

    public function saved(TicketType $ticketType): void
    {
        $ticketTypeId = $ticketType->getKey();

        if ($ticketTypeId === null) {
            return;
        }

        $this->assertConsistency->handle((string) $ticketTypeId);
    }
}
