<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Checkout;

use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\StepContributor;
use AIArmada\Events\Contracts\EventOrderDriver;
use AIArmada\Events\Contracts\EventTicketingDriver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Features\Commerce\Actions\CreateRegistrationsFromOrderAction;
use AIArmada\Events\Features\Commerce\Actions\IssueEventRegistrationPassesAction;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use Closure;

final readonly class EventsStepContributor implements StepContributor
{
    /** @return array<string, Closure(): CheckoutStepInterface> */
    public function steps(): array
    {
        $steps = [];

        $steps['create_event_registrations'] = fn (): CheckoutStepInterface => new CreateEventRegistrationsStep(
            createRegistrations: app(CreateRegistrationsFromOrderAction::class),
            registrationService: app(RegistrationServiceInterface::class),
            registrations: app(RegistrationsApi::class),
            orders: app(EventOrderDriver::class),
        );

        if ((bool) config('events.features.auto_issue_passes', false)) {
            $steps['issue_event_passes'] = fn (): CheckoutStepInterface => new IssueEventPassesStep(
                issuePasses: app(IssueEventRegistrationPassesAction::class),
                ticketing: app(EventTicketingDriver::class),
                registrations: app(RegistrationsApi::class),
                orders: app(EventOrderDriver::class),
            );
        }

        return $steps;
    }
}
