<?php

declare(strict_types=1);

namespace AIArmada\Events\Checkout;

use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\StepContributor;
use AIArmada\Events\Actions\CreateRegistrationsFromOrderAction;
use AIArmada\Events\Actions\IssueEventRegistrationPassesAction;
use AIArmada\Events\Steps\CreateEventRegistrationsStep;
use AIArmada\Events\Steps\IssueEventPassesStep;
use AIArmada\Ticketing\Contracts\PassDeliveryServiceInterface;
use Closure;

final readonly class EventsStepContributor implements StepContributor
{
    /** @return array<string, Closure(): CheckoutStepInterface> */
    public function steps(): array
    {
        $steps = [];

        $steps['create_event_registrations'] = fn (): CheckoutStepInterface => new CreateEventRegistrationsStep(
            createRegistrations: app(CreateRegistrationsFromOrderAction::class),
        );

        if ((bool) config('events.features.auto_issue_passes', false)) {
            $steps['issue_event_passes'] = fn (): CheckoutStepInterface => new IssueEventPassesStep(
                issuePasses: app(IssueEventRegistrationPassesAction::class),
                passDelivery: app(PassDeliveryServiceInterface::class),
            );
        }

        return $steps;
    }
}
