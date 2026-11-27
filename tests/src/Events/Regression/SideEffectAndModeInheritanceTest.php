<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Events\EventRegistrationApproved;
use AIArmada\Events\Features\Catalog\Actions\CloneEventAction;
use AIArmada\Events\Features\Catalog\Actions\CloneEventOccurrenceAction;
use AIArmada\Events\Features\Catalog\Actions\CreateEventOccurrenceAction;
use AIArmada\Events\Features\Catalog\Actions\CreateEventSessionAction;
use AIArmada\Events\Features\Catalog\Actions\SaveEventTemplateAction;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Notifications\EventWelcomeNotification;
use AIArmada\Ticketing\Enums\PricingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('events.notifications.welcome.enabled', true);
});

it('does not emit approval events or queue welcome notifications when the transaction rolls back', function (): void {
    EventFacade::fake([EventRegistrationApproved::class]);
    Notification::fake();

    $event = Event::factory()->create();
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'status' => 'pending',
    ]);
    $registration->participants()->create([
        'event_id' => $event->getKey(),
        'name' => 'Rolled Back',
        'is_primary' => true,
    ])->addContactMethod(ContactMethodData::email('rollback@example.com'));

    try {
        DB::transaction(function () use ($registration): void {
            app(RegistrationServiceInterface::class)->approve((string) $registration->getKey());

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect($registration->fresh()->status->getValue())->toBe('pending');

    EventFacade::assertNotDispatched(EventRegistrationApproved::class);
    Notification::assertNothingSent();
});

it('dispatches approval side effects once in the approving owner context after commit', function (): void {
    config()->set('events.features.owner.enabled', true);
    Notification::fake();

    $owner = User::factory()->create();
    $observedOwners = [];
    EventFacade::listen(EventRegistrationApproved::class, function () use (&$observedOwners): void {
        $observedOwners[] = OwnerContext::resolve()?->getKey();
    });

    DB::transaction(function () use ($owner): void {
        OwnerContext::withOwner($owner, function (): void {
            $event = Event::factory()->create();
            $registration = EventRegistration::factory()->create([
                'event_id' => $event->getKey(),
                'status' => 'pending',
            ]);
            $registration->participants()->create([
                'event_id' => $event->getKey(),
                'name' => 'Committed',
                'is_primary' => true,
            ])->addContactMethod(ContactMethodData::email('committed@example.com'));

            $service = app(RegistrationServiceInterface::class);
            $service->approve((string) $registration->getKey());
            $service->approve((string) $registration->getKey());
        });
    });

    expect($observedOwners)->toBe([$owner->getKey()]);
    Notification::assertSentOnDemand(EventWelcomeNotification::class);
});

it('allows occurrence and session create overrides for pricing, registration and free pass modes', function (): void {
    $event = Event::factory()->create(['issue_passes_for_free' => true]);

    $occurrence = app(CreateEventOccurrenceAction::class)->handle($event, [
        'title' => 'Overridden Occurrence',
        'pricing_mode' => PricingMode::Free,
        'registration_mode' => 'optional',
        'issue_passes_for_free' => false,
    ]);

    expect($occurrence->pricing_mode)->toBe(PricingMode::Free)
        ->and($occurrence->registration_mode?->value)->toBe('optional')
        ->and($occurrence->issue_passes_for_free)->toBeFalse()
        ->and($occurrence->shouldIssuePassesForFree())->toBeFalse();

    $session = app(CreateEventSessionAction::class)->handle($occurrence, [
        'title' => 'Overridden Session',
        'pricing_mode' => PricingMode::Paid,
        'registration_mode' => 'none',
        'issue_passes_for_free' => true,
    ]);

    expect($session->pricing_mode)->toBe(PricingMode::Paid)
        ->and($session->registration_mode?->value)->toBe('none')
        ->and($session->issue_passes_for_free)->toBeTrue()
        ->and($session->shouldIssuePassesForFree())->toBeTrue();
});

it('inherits free pass policy through nullable occurrence and session values', function (): void {
    $event = Event::factory()->create(['issue_passes_for_free' => false]);
    $occurrence = app(CreateEventOccurrenceAction::class)->handle($event, ['title' => 'Inheriting']);
    $session = app(CreateEventSessionAction::class)->handle($occurrence, ['title' => 'Inheriting Session']);

    expect($occurrence->issue_passes_for_free)->toBeNull()
        ->and($occurrence->shouldIssuePassesForFree())->toBeFalse()
        ->and($session->issue_passes_for_free)->toBeNull()
        ->and($session->shouldIssuePassesForFree())->toBeFalse();
});

it('keeps explicit inherited parent pricing when ticket type derivation is disabled', function (): void {
    config()->set('events.features.free_only.auto_derive_pricing_from_ticket_types', false);

    $event = Event::factory()->create(['pricing_mode' => PricingMode::Free]);
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->getKey()]);
    $session = EventSession::factory()->create([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $occurrence->getKey(),
    ]);

    expect($occurrence->effectivePricingMode())->toBe(PricingMode::Free)
        ->and($session->effectivePricingMode())->toBe(PricingMode::Free);
});

it('clones and templates explicit false and nullable free pass policy without changing it', function (): void {
    $event = Event::factory()->create(['issue_passes_for_free' => null]);
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->getKey(),
        'issue_passes_for_free' => false,
        'pricing_mode' => PricingMode::Free,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $clone = app(CloneEventOccurrenceAction::class)->handle($occurrence, ['title' => 'Cloned Occurrence']);

    expect($clone->issue_passes_for_free)->toBeFalse()
        ->and($clone->pricing_mode)->toBe(PricingMode::Free);

    $template = app(SaveEventTemplateAction::class)->createFromOccurrence($occurrence);
    $payload = $template->payload;

    expect($payload['issue_passes_for_free'])->toBeFalse()
        ->and($payload['pricing_mode'])->toBe('free');
});

it('preserves null event free pass policy on clone', function (): void {
    $event = Event::factory()->create(['issue_passes_for_free' => null]);

    $clone = app(CloneEventAction::class)->handle($event);

    expect($clone->issue_passes_for_free)->toBeNull();
});
