<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Listeners;

use AIArmada\Events\Events\EventFreeRegistrationConfirmed;
use AIArmada\Events\Features\Commerce\Actions\IssueEventRegistrationPassesAction;

final class IssueEventPassesOnFreeRegistrationConfirmed
{
    public function __construct(
        private readonly IssueEventRegistrationPassesAction $issuePasses,
    ) {}

    public function handle(EventFreeRegistrationConfirmed $event): void
    {
        if (! $event->withPass) {
            return;
        }

        $this->issuePasses->handle($event->registrationId, respectFreePassPolicy: true);
    }
}
