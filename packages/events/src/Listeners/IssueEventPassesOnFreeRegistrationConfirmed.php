<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\Events\Actions\IssueEventRegistrationPassesAction;
use AIArmada\Events\Events\EventFreeRegistrationConfirmed;

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

        $registration = $event->registration;

        $session = $registration->session;
        $occurrence = $registration->occurrence;
        $eventModel = $registration->event;

        if ($session !== null && $session->shouldIssuePassesForFree() === false) {
            return;
        }

        if ($session === null && $occurrence !== null && $occurrence->shouldIssuePassesForFree() === false) {
            return;
        }

        if ($session === null && $occurrence === null && $eventModel->shouldIssuePassesForFree() === false) {
            return;
        }

        $this->issuePasses->handle($registration);
    }
}
