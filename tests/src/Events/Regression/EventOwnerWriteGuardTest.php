<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Features\Catalog\Actions\CreateEventSessionAction;
use AIArmada\Events\Features\Catalog\Actions\UpdateEventOccurrenceAction;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Governance\Models\EventSubmission;
use AIArmada\Events\Features\Governance\Models\EventSubmissionAttachment;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', true);
    config()->set('events.features.owner.include_global', false);
});

it('does not let include-global reads authorize writes against global parents', function (): void {
    config()->set('events.features.owner.include_global', true);

    $owner = User::factory()->create();

    $submission = OwnerContext::withOwner(null, function (): EventSubmission {
        return EventSubmission::factory()->create([
            'target_type' => null,
            'target_id' => null,
            'submission_data' => ['title' => 'Global submission'],
        ]);
    });

    expect(fn () => OwnerContext::withOwner($owner, function () use ($submission): void {
        EventSubmissionAttachment::factory()->create([
            'event_submission_id' => $submission->getKey(),
            'name' => 'cross-owner.pdf',
        ]);
    }))->toThrow(AuthorizationException::class);
});

it('rejects reparenting a global occurrence into the current owner', function (): void {
    config()->set('events.features.owner.include_global', true);

    $owner = User::factory()->create();

    [$globalOccurrence, $ownedEvent] = OwnerContext::withOwner(null, function () use ($owner): array {
        $globalEvent = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $globalEvent->getKey()]);

        $ownedEvent = OwnerContext::withOwner($owner, fn (): Event => Event::factory()->create());

        return [$occurrence, $ownedEvent];
    });

    expect(fn () => OwnerContext::withOwner($owner, function () use ($globalOccurrence, $ownedEvent): void {
        $occurrence = EventOccurrence::query()->findOrFail($globalOccurrence->getKey());
        $occurrence->event_id = $ownedEvent->getKey();
        $occurrence->save();
    }))->toThrow(AuthorizationException::class);
});

it('resolves the persisted occurrence parent when creating a session', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $occurrence = OwnerContext::withOwner($ownerA, function (): EventOccurrence {
        $event = Event::factory()->create();

        return EventOccurrence::factory()->create(['event_id' => $event->getKey()]);
    });

    $eventB = OwnerContext::withOwner($ownerB, fn (): Event => Event::factory()->create());

    $occurrence->event_id = $eventB->getKey();

    expect(fn () => OwnerContext::withOwner($ownerB, function () use ($occurrence): void {
        app(CreateEventSessionAction::class)->handle($occurrence, ['title' => 'Cross owner session']);
    }))->toThrow(AuthorizationException::class);
});

it('ignores dirty parent state when updating an occurrence', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $occurrence = OwnerContext::withOwner($ownerA, function (): EventOccurrence {
        $event = Event::factory()->create();

        return EventOccurrence::factory()->create([
            'event_id' => $event->getKey(),
            'title' => 'Original title',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);
    });

    $eventB = OwnerContext::withOwner($ownerB, fn (): Event => Event::factory()->create());
    $persistedEventId = $occurrence->event_id;

    $occurrence->event_id = $eventB->getKey();

    $result = OwnerContext::withOwner($ownerA, fn (): array => app(UpdateEventOccurrenceAction::class)->handle($occurrence, [
        'title' => 'Persisted parent update',
    ]));

    expect($result['occurrence']->event_id)->toBe($persistedEventId)
        ->and($result['occurrence']->event_id)->not->toBe($eventB->getKey())
        ->and($result['occurrence']->title)->toBe('Persisted parent update');
});

it('does not describe event scopes outside the current owner context', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $event = OwnerContext::withOwner($ownerA, fn (): Event => Event::factory()->create());

    expect(OwnerContext::withOwner(
        $ownerB,
        fn (): mixed => app(CatalogApi::class)->describeScope('event', (string) $event->getKey()),
    ))->toBeNull();
});

it('locks registrations only inside the current owner context', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $registration = OwnerContext::withOwner($ownerA, function (): EventRegistration {
        $event = Event::factory()->create();

        return EventRegistration::factory()->create(['event_id' => $event->getKey()]);
    });

    expect(fn () => OwnerContext::withOwner($ownerB, function () use ($registration): void {
        app(RegistrationsApi::class)->lock((string) $registration->getKey());
    }))->toThrow(ModelNotFoundException::class);
});

it('does not let a submission target owner delete an event-bound submission from another owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $submission = OwnerContext::withOwner($ownerA, function () use ($ownerB): EventSubmission {
        $event = Event::factory()->create();

        return EventSubmission::factory()->create([
            'event_id' => $event->getKey(),
            'target_type' => $ownerB->getMorphClass(),
            'target_id' => $ownerB->getKey(),
        ]);
    });

    expect(fn () => OwnerContext::withOwner($ownerB, fn () => $submission->delete()))
        ->toThrow(AuthorizationException::class);

    $submission->event_id = null;

    expect(fn () => OwnerContext::withOwner($ownerB, fn () => $submission->delete()))
        ->toThrow(AuthorizationException::class);
});

it('requires write access to an attachment’s original submission', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $attachment = OwnerContext::withOwner($ownerA, function () use ($ownerA): EventSubmissionAttachment {
        $submission = EventSubmission::factory()->create([
            'target_type' => $ownerA->getMorphClass(),
            'target_id' => $ownerA->getKey(),
        ]);

        return EventSubmissionAttachment::factory()->create(['event_submission_id' => $submission->getKey()]);
    });

    OwnerContext::withOwner($ownerB, function () use ($ownerB, $attachment): void {
        $submission = EventSubmission::factory()->create([
            'target_type' => $ownerB->getMorphClass(),
            'target_id' => $ownerB->getKey(),
        ]);

        $attachment->event_submission_id = $submission->getKey();

        expect(fn () => $attachment->save())->toThrow(AuthorizationException::class);
    });
});

it('resolves registration scopes only inside the current owner context', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $event = OwnerContext::withOwner($ownerA, fn (): Event => Event::factory()->create());

    $scope = OwnerContext::withOwner(
        $ownerA,
        fn (): mixed => app(EventRegistrationScopeResolver::class)
            ->resolve('event', (string) $event->getKey()),
    );

    expect($scope->eventId)->toBe($event->getKey());

    expect(fn () => OwnerContext::withOwner($ownerB, fn (): mixed => app(EventRegistrationScopeResolver::class)
        ->resolve('event', (string) $event->getKey())))
        ->toThrow(AuthorizationException::class);
});
