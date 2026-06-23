<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\Events\Contracts\EventPassIssuer;
use AIArmada\Events\Events\EventFreeRegistrationConfirmed;

final class IssueEventPassesOnFreeRegistrationConfirmed
{
    public function __construct(
        private readonly EventPassIssuer $passIssuer,
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

        $this->passIssuer->issuePassesFor($registration);
    }
}
