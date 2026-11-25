<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Listeners;

use AIArmada\Events\Events\EventRegistrationRefunded;
use AIArmada\Events\Features\Commerce\Actions\RestoreTicketInventoryOnRegistrationRefundedAction;

final class RestoreTicketInventoryOnRegistrationRefunded
{
    public function __construct(
        private readonly RestoreTicketInventoryOnRegistrationRefundedAction $restoreInventory,
    ) {}

    public function handle(EventRegistrationRefunded $event): void
    {
        $this->restoreInventory->handle($event->registrationId);
    }
}
