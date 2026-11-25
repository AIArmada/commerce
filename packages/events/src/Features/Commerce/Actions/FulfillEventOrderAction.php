<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Platform\EventWriteGuard;

final class FulfillEventOrderAction
{
    public function __construct(
        private readonly RegistrationsApi $registrations,
        private readonly FulfillEventOrderItemAction $fulfillItem,
    ) {}

    public function handle(string $registrationId): void
    {
        $summary = $this->registrations->summarize($registrationId);

        if ($summary === null) {
            return;
        }

        EventWriteGuard::findOrFail($summary->eventId);

        foreach ($this->registrations->orderItemsForRegistration($registrationId) as $item) {
            $this->fulfillItem->handle($item->id);
        }
    }
}
