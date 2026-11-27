<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Policies\EventPolicy;
use AIArmada\Events\Features\Governance\Models\EventSubmission;
use AIArmada\Events\Features\Governance\Policies\EventSubmissionPolicy;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\Policies\EventRegistrationPolicy;

it('allows only the resolved owner to mutate an event by default', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($otherUser, $owner): void {
        $event = Event::factory()->create();
        $policy = new EventPolicy;

        expect($policy->view($owner, $event))->toBeTrue()
            ->and($policy->update($owner, $event))->toBeTrue()
            ->and($policy->publish($owner, $event))->toBeTrue()
            ->and($policy->archive($owner, $event))->toBeTrue()
            ->and($policy->cancel($owner, $event))->toBeTrue()
            ->and($policy->update($otherUser, $event))->toBeFalse()
            ->and($policy->publish($otherUser, $event))->toBeFalse();
    });
});

it('allows public event viewing without granting mutation access', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($viewer): void {
        $event = Event::factory()->published()->create(['visibility' => Event::PUBLIC]);
        $policy = new EventPolicy;

        expect($policy->view($viewer, $event))->toBeTrue()
            ->and($policy->update($viewer, $event))->toBeFalse();
    });
});

it('denies unrelated viewers registration records for public events', function (): void {
    $owner = User::factory()->create();
    $registrant = User::factory()->create();
    $viewer = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($registrant, $viewer): void {
        $event = Event::factory()->published()->create(['visibility' => Event::PUBLIC]);
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->getKey(),
            'registrant_type' => $registrant->getMorphClass(),
            'registrant_id' => $registrant->getKey(),
        ]);

        $policy = new EventRegistrationPolicy;

        expect($policy->view($viewer, $registration))->toBeFalse()
            ->and($policy->view($registrant, $registration))->toBeTrue();
    });
});

it('grants admissions and refund abilities through the owner and registrant paths', function (): void {
    $owner = User::factory()->create();
    $registrant = User::factory()->create();
    $stranger = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($owner, $registrant, $stranger): void {
        $event = Event::factory()->create();
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->getKey(),
            'registrant_type' => $registrant->getMorphClass(),
            'registrant_id' => $registrant->getKey(),
        ]);

        $eventPolicy = new EventPolicy;
        $registrationPolicy = new EventRegistrationPolicy;

        expect($eventPolicy->manageAdmissions($owner, $event))->toBeTrue()
            ->and($eventPolicy->manageAdmissions($stranger, $event))->toBeFalse()
            ->and($registrationPolicy->requestRefund($registrant, $registration))->toBeTrue()
            ->and($registrationPolicy->requestRefund($owner, $registration))->toBeTrue()
            ->and($registrationPolicy->requestRefund($stranger, $registration))->toBeFalse();
    });
});

it('denies unrelated viewers submission records for public events', function (): void {
    $owner = User::factory()->create();
    $submitter = User::factory()->create();
    $viewer = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($owner, $submitter, $viewer): void {
        $event = Event::factory()->published()->create(['visibility' => Event::PUBLIC]);
        $submission = EventSubmission::factory()->create([
            'event_id' => $event->getKey(),
            'target_type' => $owner->getMorphClass(),
            'target_id' => $owner->getKey(),
            'submitter_type' => $submitter->getMorphClass(),
            'submitter_id' => $submitter->getKey(),
        ]);

        $policy = new EventSubmissionPolicy;

        expect($policy->view($viewer, $submission))->toBeFalse()
            ->and($policy->view($submitter, $submission))->toBeTrue()
            ->and($policy->view($owner, $submission))->toBeTrue();
    });
});
