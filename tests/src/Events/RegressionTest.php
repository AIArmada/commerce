<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Actions\ApproveAssignmentRequestAction;
use AIArmada\Events\Actions\CloneEventContentsAction;
use AIArmada\Events\Actions\PromoteInterestedToConfirmedAction;
use AIArmada\Events\Actions\RegisterForFreeAction;
use AIArmada\Events\Actions\SubmitAssignmentRequestAction;
use AIArmada\Events\Actions\SyncEventClassificationsAction;
use AIArmada\Events\Actions\UpdateEventOccurrenceAction;
use AIArmada\Events\Actions\UpdateEventSessionAction;
use AIArmada\Events\Contracts\EventCheckInService;
use AIArmada\Events\Contracts\EventRegistrationEligibility;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Exceptions\EventRegistrationNotAvailableException;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventLocation;
use AIArmada\Events\Models\EventManagementAssignment;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventOrganizer;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventRegistrationItem;
use AIArmada\Events\Models\EventRegistrationParticipant;
use AIArmada\Events\Models\EventSearchDocument;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventSubmission;
use AIArmada\Events\Policies\EventOccurrencePolicy;
use AIArmada\Events\Policies\EventRegistrationPolicy;
use AIArmada\Events\Policies\EventSessionPolicy;
use AIArmada\Events\Policies\EventSubmissionPolicy;
use AIArmada\Events\Services\EloquentEventSearchEngine;
use AIArmada\Events\Services\EventQueryService;
use AIArmada\Events\Services\EventSearchDocumentBuilder;
use AIArmada\Ticketing\Models\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', true);
});

// Classification sync is owner-guarded.
it('rejects classification syncs for events outside the owner scope', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $eventB = OwnerContext::withOwner($ownerB, fn (): Event => Event::factory()->create());

    OwnerContext::withOwner($ownerA, function () use ($eventB): void {
        app(SyncEventClassificationsAction::class)->handle($eventB, ['topic' => ['Music']]);
    });
})->throws(AuthorizationException::class);

// Sync dedupes terms through the unique code and stays idempotent.
it('syncs classifications idempotently without duplicating terms', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();

        $action = app(SyncEventClassificationsAction::class);
        $action->handle($event, ['topic' => ['Music', 'music ', 'Arts']]);
        $action->handle($event, ['topic' => ['Music', 'Arts']]);

        expect($event->classifications()->count())->toBe(2)
            ->and(DB::table('event_terms')->count())->toBe(2);
    });
});

// Items must reference a ticket type from the same event.
it('rejects registration items with a ticket type from another event', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $other = Event::factory()->create();
        $foreignTicket = createEventTicketType($other);

        app(RegistrationServiceInterface::class)->register([
            'event_id' => $event->id,
            'registration_type' => 'individual',
            'status' => 'pending',
            'source' => 'website',
            'total_participants' => 1,
            'items' => [
                ['ticket_type_id' => $foreignTicket->id, 'quantity' => 1],
            ],
        ]);
    });
})->throws(InvalidArgumentException::class, 'same event');

// Money stays non-negative integer minor units.
it('rejects forged registration totals', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();

        app(RegistrationServiceInterface::class)->register([
            'event_id' => $event->id,
            'registration_type' => 'individual',
            'status' => 'pending',
            'source' => 'website',
            'total_participants' => 1,
            'total_amount' => -500,
        ]);
    });
})->throws(InvalidArgumentException::class, 'non-negative integer');

// Registrant morphs must resolve to real models.
it('rejects registrations with an unresolvable registrant type', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();

        app(RegistrationServiceInterface::class)->register([
            'event_id' => $event->id,
            'registration_type' => 'individual',
            'status' => 'pending',
            'source' => 'website',
            'total_participants' => 1,
            'registrant_type' => 'not-a-model',
            'registrant_id' => (string) Str::uuid(),
        ]);
    });
})->throws(InvalidArgumentException::class, 'Eloquent model');

// Participant emails must be valid.
it('rejects participants with an invalid email address', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();

        app(RegistrationServiceInterface::class)->register([
            'event_id' => $event->id,
            'registration_type' => 'individual',
            'status' => 'pending',
            'source' => 'website',
            'total_participants' => 1,
            'participants' => [
                ['name' => 'Bad Email', 'email' => 'not-an-email'],
            ],
        ]);
    });
})->throws(InvalidArgumentException::class, 'valid email address');

// Forged item ids and scope columns cannot override the parent scope.
it('ignores forged item ids and scope columns on registration items', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $ticketType = createEventTicketType($event);

        $registration = app(RegistrationServiceInterface::class)->register([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'registration_type' => 'individual',
            'status' => 'pending',
            'source' => 'website',
            'total_participants' => 1,
            'items' => [[
                'id' => (string) Str::uuid(),
                'ticket_type_id' => $ticketType->id,
                'quantity' => 1,
                'event_id' => (string) Str::uuid(),
                'event_occurrence_id' => null,
                'event_session_id' => (string) Str::uuid(),
            ]],
        ]);

        $item = $registration->items()->firstOrFail();

        expect($item->event_id)->toBe($event->id)
            ->and($item->event_occurrence_id)->toBe($occurrence->id)
            ->and($item->event_session_id)->toBeNull();
    });
});

// Bundle parents must belong to the same event.
it('rejects bundle parents from another event', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $other = Event::factory()->create();
        $foreign = EventRegistration::factory()->create(['event_id' => $other->id]);

        app(RegistrationServiceInterface::class)->register([
            'event_id' => $event->id,
            'registration_type' => 'individual',
            'status' => 'pending',
            'source' => 'website',
            'total_participants' => 1,
            'parent_registration_id' => $foreign->id,
        ]);
    });
})->throws(InvalidArgumentException::class, 'parent registration');

// Order-item registrations default to the configured currency.
it('defaults order-item registration currency to the configured default', function (): void {
    config()->set('events.defaults.currency', 'MYR');

    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();

        app(RegistrationServiceInterface::class)->createFromOrderItem([
            'event_id' => $event->id,
            'quantity' => 1,
        ]);

        expect(EventRegistration::query()->latest('created_at')->first()?->currency)->toBe('MYR');
    });
});

// Update actions route status through the state machine.
it('rejects illegal session status jumps in the update action', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => 'completed',
            'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
            'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
        ]);

        app(UpdateEventSessionAction::class)->handle($session, ['status' => 'draft']);
    });
})->throws(TransitionNotFound::class);

it('rejects unknown occurrence statuses in the update action', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'status' => 'scheduled',
            'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
            'ends_at' => CarbonImmutable::parse('2026-07-01 11:00:00'),
        ]);

        app(UpdateEventOccurrenceAction::class)->handle($occurrence, ['status' => 'not-a-status']);
    });
})->throws(InvalidArgumentException::class, 'Unknown occurrence status');

// Eligibility spans event, occurrence, and session.
it('blocks registrations for cancelled sessions and cancelled events', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->published()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'status' => 'published',
        ]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => 'cancelled',
            'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
            'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
        ]);

        $eligibility = app(EventRegistrationEligibility::class);
        $scope = app(EventRegistrationScopeResolver::class)->resolve($session);

        expect(fn () => $eligibility->ensureEligible($scope))
            ->toThrow(EventRegistrationNotAvailableException::class, 'session');

        $cancelledEvent = Event::factory()->create(['status' => 'cancelled']);
        $cancelledScope = app(EventRegistrationScopeResolver::class)->resolve($cancelledEvent);

        expect(fn () => $eligibility->ensureEligible($cancelledScope))
            ->toThrow(EventRegistrationNotAvailableException::class, 'event');
    });
});

it('keeps accepting registrations on draft events for staff flows', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create(['status' => 'draft']);
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'status' => 'scheduled',
        ]);

        $eligibility = app(EventRegistrationEligibility::class);
        $scope = app(EventRegistrationScopeResolver::class)->resolve($occurrence);

        $eligibility->ensureEligible($scope);

        expect(true)->toBeTrue();
    });
});

// Check-in attendee morphs and fields are validated.
it('rejects check-ins with an arbitrary attendee morph type', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        app(EventCheckInService::class)->checkIn([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'attendee_type' => 'not-a-model',
            'attendee_id' => (string) Str::uuid(),
        ]);
    });
})->throws(InvalidArgumentException::class, 'Eloquent model');

it('rejects check-ins with oversized notes or non-array metadata', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $service = app(EventCheckInService::class);

        expect(fn () => $service->checkIn([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'notes' => str_repeat('n', 5001),
        ]))->toThrow(InvalidArgumentException::class, '5000');

        expect(fn () => $service->checkIn([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'metadata' => 'not-an-array',
        ]))->toThrow(InvalidArgumentException::class, 'array');

        expect(fn () => $service->checkIn([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'verified_by_user_id' => 'not-a-uuid',
        ]))->toThrow(InvalidArgumentException::class, 'UUID');
    });
});

// Idempotency keys live in an indexed column.
it('stores the idempotency key in its own column', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        app(RegisterForFreeAction::class)->execute(
            target: $occurrence,
            participants: [['name' => 'Idempotent Irene', 'is_primary' => true]],
            options: ['idempotency_key' => 'repair-regression-key'],
        );

        expect(EventRegistration::query()->where('idempotency_key', 'repair-regression-key')->count())->toBe(1);
    });
});

// Query and search result sets are bounded.
it('bounds published listings and search results', function (): void {
    OwnerContext::withOwner(null, function (): void {
        Event::factory()->count(110)->published()->create();

        expect(app(EventQueryService::class)->findPublished())->toHaveCount(100)
            ->and(app(EloquentEventSearchEngine::class)->search([]))->toHaveCount(25)
            ->and(app(EloquentEventSearchEngine::class)->search(['limit' => 500]))->toHaveCount(100);
    });
});

// Slug lookups resolve deterministically.
it('resolves duplicate slugs to the earliest created event', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $first = Event::factory()->create(['slug' => 'dupe-slug', 'created_at' => now()->subDay()]);
        Event::factory()->create(['slug' => 'dupe-slug']);

        expect(app(EventQueryService::class)->findBySlug('dupe-slug')?->id)->toBe($first->id);
    });
});

// The finalize command runs under explicit owner context.
it('finalize command fails without an owner context and runs with global context', function (): void {
    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    $this->artisan('events:finalize-orders', ['--dry-run' => true])
        ->assertFailed();

    $this->artisan('events:finalize-orders', ['--global' => true, '--dry-run' => true])
        ->assertSuccessful();
});

// Child models carry owner-derived policies.
it('authorizes child models through their event owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($other, $owner): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
            'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
        ]);
        $registration = EventRegistration::factory()->create(['event_id' => $event->id]);

        expect((new EventOccurrencePolicy)->update($owner, $occurrence))->toBeTrue()
            ->and((new EventOccurrencePolicy)->update($other, $occurrence))->toBeFalse()
            ->and((new EventSessionPolicy)->delete($owner, $session))->toBeTrue()
            ->and((new EventSessionPolicy)->delete($other, $session))->toBeFalse()
            ->and((new EventRegistrationPolicy)->update($owner, $registration))->toBeTrue()
            ->and((new EventRegistrationPolicy)->update($other, $registration))->toBeFalse();
    });
});

it('authorizes submissions through their event or target owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($other, $owner): void {
        $submission = EventSubmission::factory()->create([
            'target_type' => $owner->getMorphClass(),
            'target_id' => $owner->getKey(),
        ]);

        expect((new EventSubmissionPolicy)->update($owner, $submission))->toBeTrue()
            ->and((new EventSubmissionPolicy)->update($other, $submission))->toBeFalse();
    });
});

// Clones are guarded, atomic, and reject unknown relations.
it('rejects clones with unknown relations', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();

        app(CloneEventContentsAction::class)->handle($event->id, $event->id, relations: ['nope']);
    });
})->throws(InvalidArgumentException::class, 'Unknown cloneable');

it('rejects clones into events outside the owner scope', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $eventA = OwnerContext::withOwner($ownerA, fn (): Event => Event::factory()->create());
    $eventB = OwnerContext::withOwner($ownerB, fn (): Event => Event::factory()->create());

    OwnerContext::withOwner($ownerA, function () use ($eventA, $eventB): void {
        app(CloneEventContentsAction::class)->handle($eventA->id, $eventB->id);
    });
})->throws(AuthorizationException::class);

// Owner tuples cannot be mass-assigned.
it('drops forged owner tuples on event creation', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $event = OwnerContext::withOwner($owner, fn (): Event => Event::query()->create([
        'title' => 'Forged Owner Event',
        'status' => 'draft',
        'visibility' => 'private',
        'owner_type' => $other->getMorphClass(),
        'owner_id' => $other->getKey(),
    ]));

    expect($event->owner_type)->toBe($owner->getMorphClass())
        ->and((string) $event->owner_id)->toBe((string) $owner->getKey());
});

// Capacity math is covered by composite indexes.
it('indexes registration scope and status for capacity math', function (): void {
    $indexes = array_map(
        static fn (array $index): string => is_string($index['name'] ?? null) ? $index['name'] : '',
        Schema::getIndexes('event_registrations'),
    );

    expect($indexes)->toContain('event_registrations_occurrence_status_index')
        ->and($indexes)->toContain('event_registrations_session_status_index');
});

// Missing authz roles warn loudly instead of vanishing.
it('warns when the authz role for a management assignment is missing', function (): void {
    config()->set('authz.scopes.enabled', true);
    Log::spy();

    OwnerContext::withOwner(null, function (): void {
        $manageable = EventOrganizer::create([
            'name' => 'Warn Org',
            'slug' => 'warn-org-' . uniqid(),
            'status' => 'active',
            'visibility' => 'public',
            'sort_order' => 0,
        ]);
        $requestor = Customer::create([
            'first_name' => 'Warn',
            'last_name' => 'Requestor',
            'email' => 'warn-' . uniqid() . '@example.com',
        ]);
        $requestor->forceFill(['status' => 'active'])->save();

        $request = (new SubmitAssignmentRequestAction)->handle($manageable, $requestor, 'Please.');
        (new ApproveAssignmentRequestAction)->handle($request, $requestor, 'ghost-role-' . uniqid());
    });

    Log::shouldHaveReceived('warning')->once();
});

// Dead input DTOs stay deleted.
it('removed the dead registration input DTOs', function (): void {
    expect(class_exists('AIArmada\Events\Data\RegisterInput'))->toBeFalse()
        ->and(class_exists('AIArmada\Events\Data\ParticipantInput'))->toBeFalse()
        ->and(class_exists('AIArmada\Events\Data\CheckInInput'))->toBeFalse();
});

// Deleting an event cascades through the owned subtree.
it('cascades event deletes through occurrences, sessions, and registrations', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $session = EventSession::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
            'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
        ]);
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
        ]);
        $ticketType = createEventTicketType($occurrence);
        $participant = $registration->participants()->create(['name' => 'Cascade Casey']);
        $registration->items()->create(['ticket_type_id' => $ticketType->id, 'quantity' => 1]);
        $location = EventLocation::factory()->create([
            'event_id' => $event->id,
            'label' => 'Cascade Hall',
        ]);

        $event->delete();

        expect(EventOccurrence::query()->whereKey($occurrence->id)->exists())->toBeFalse()
            ->and(EventSession::query()->whereKey($session->id)->exists())->toBeFalse()
            ->and(EventRegistration::query()->whereKey($registration->id)->exists())->toBeFalse()
            ->and(EventRegistrationParticipant::query()->whereKey($participant->id)->exists())->toBeFalse()
            ->and(EventRegistrationItem::query()->where('event_registration_id', $registration->id)->exists())->toBeFalse()
            ->and(EventLocation::query()->whereKey($location->id)->exists())->toBeFalse()
            ->and(TicketType::query()->whereKey($ticketType->id)->exists())->toBeFalse();
    });
});

// Null-event morph rows stay isolated by their morph target.
it('isolates null-event morph rows by their owner-visible target', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    OwnerContext::withOwner($ownerA, function (): void {
        $organizer = EventOrganizer::create([
            'name' => 'Null Event Org',
            'slug' => 'null-event-org-' . uniqid(),
            'status' => 'active',
            'visibility' => 'public',
            'sort_order' => 0,
        ]);

        EventManagementAssignment::query()->create([
            'manageable_type' => $organizer->getMorphClass(),
            'manageable_id' => $organizer->getKey(),
            'manager_type' => $organizer->getMorphClass(),
            'manager_id' => $organizer->getKey(),
            'role' => 'manager',
            'event_id' => null,
        ]);
    });

    expect(OwnerContext::withOwner($ownerA, fn (): int => EventManagementAssignment::query()->count()))->toBe(1)
        ->and(OwnerContext::withOwner($ownerB, fn (): int => EventManagementAssignment::query()->count()))->toBe(0);
});

// Search document removal only touches the target event.
it('removes search documents for one event without touching others', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create(['title' => 'Keep Me Searchable']);
        $other = Event::factory()->create(['title' => 'Remove Me']);

        $builder = app(EventSearchDocumentBuilder::class);
        $builder->buildForEvent($event);
        $builder->buildForEvent($other);

        $builder->remove($other);

        expect(EventSearchDocument::query()->where('event_id', $other->id)->exists())->toBeFalse()
            ->and(EventSearchDocument::query()->where('event_id', $event->id)->exists())->toBeTrue();
    });
});

// Unbounded child payloads are rejected.
it('rejects registrations with unbounded child payloads', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->create();

        app(RegistrationServiceInterface::class)->register([
            'event_id' => $event->id,
            'registration_type' => 'individual',
            'status' => 'pending',
            'source' => 'website',
            'total_participants' => 1,
            'participants' => array_fill(0, 1001, ['name' => 'Crowd']),
        ]);
    });
})->throws(InvalidArgumentException::class, 'more than');

// Failed promotions leave the registration untouched.
it('leaves interested registrations untouched when promotion hits capacity', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'capacity' => 1,
        ]);
        EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => 'confirmed',
            'total_participants' => 1,
        ]);
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => 'interested',
        ]);

        try {
            app(PromoteInterestedToConfirmedAction::class)->execute($registration);
            $this->fail('Expected promotion to exceed capacity.');
        } catch (EventCapacityExceededException) {
            expect($registration->fresh()->status->getValue())->toBe('interested');
        }
    });
});
