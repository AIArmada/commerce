<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Listeners;

use AIArmada\Events\Features\Commerce\Actions\AllocateEventSeatsOnPassIssuedAction;
use AIArmada\Ticketing\Events\PassIssued;

final class AllocateEventSeatsOnPassIssued
{
    public function __construct(
        private readonly AllocateEventSeatsOnPassIssuedAction $allocateAction,
    ) {}

    public function handle(PassIssued $event): void
    {
        $passId = $event->pass->getKey();

        if ($passId === null) {
            return;
        }

        $this->allocateAction->handle((string) $passId);
    }
}
