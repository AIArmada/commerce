<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Changes\Actions\DispatchEventChangeChainAction;
use AIArmada\Events\Features\Changes\Models\EventChangeLog;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('records add and remove entries from the union of changed keys', function (): void {
    $event = Event::factory()->create();

    app(DispatchEventChangeChainAction::class)->handle(
        eventId: (string) $event->getKey(),
        changeType: 'venue_changed',
        impactLevel: 'medium',
        oldValue: ['venue' => 'Old Hall'],
        newValue: ['venue' => 'New Hall', 'address' => '1 New Street'],
    );

    $update = EventChangeLog::query()->latest('created_at')->firstOrFail()->eventUpdate;
    $items = $update->items()->orderBy('field_key')->get()->keyBy('field_key');

    expect($items->keys()->all())->toBe(['address', 'venue'])
        ->and($items['venue']->old_value)->toBe('Old Hall')
        ->and($items['venue']->new_value)->toBe('New Hall')
        ->and($items['address']->old_value)->toBeNull()
        ->and($items['address']->new_value)->toBe('1 New Street');
});

it('records reschedule starts_at and ends_at as parallel old and new values', function (): void {
    $event = Event::factory()->published()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->getKey(),
        'starts_at' => now()->addDay()->startOfHour(),
        'ends_at' => now()->addDay()->addHour()->startOfHour(),
    ]);

    app(EventLifecycleWorkflow::class)->reschedule(
        'occurrence',
        (string) $occurrence->getKey(),
        now()->addDays(2)->startOfHour(),
        now()->addDays(2)->addHours(2)->startOfHour(),
    );

    $log = EventChangeLog::query()
        ->where('change_type', 'rescheduled')
        ->latest('created_at')
        ->firstOrFail();
    $items = $log->eventUpdate->items()->orderBy('field_key')->get()->keyBy('field_key');

    expect($items->keys()->all())->toBe(['ends_at', 'starts_at'])
        ->and($items['starts_at']->old_value)->not->toBeNull()
        ->and($items['starts_at']->new_value)->not->toBeNull()
        ->and($items['starts_at']->old_value)->not->toBe($items['starts_at']->new_value)
        ->and($items['ends_at']->old_value)->not->toBeNull()
        ->and($items['ends_at']->new_value)->not->toBeNull();
});

it('persists explicit participant scope, inherits absent scope, and strips caller system ids', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->getKey()]);
    $session = EventSession::factory()->create([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $occurrence->getKey(),
    ]);

    $registrationId = app(RegistrationServiceInterface::class)->register([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $occurrence->getKey(),
        'registration_type' => 'individual',
        'status' => 'confirmed',
        'source' => 'website',
        'total_participants' => 2,
        'participants' => [
            [
                'id' => (string) Str::uuid(),
                'event_registration_id' => (string) Str::uuid(),
                'event_session_id' => $session->getKey(),
                'name' => 'Scoped Participant',
                'is_primary' => true,
            ],
            [
                'name' => 'Inherited Participant',
            ],
        ],
    ]);

    $registration = EventRegistration::query()->findOrFail($registrationId);
    $participants = $registration->participants()->orderBy('created_at')->get();

    expect($participants[0]->event_session_id)->toBe($session->getKey())
        ->and($participants[0]->event_occurrence_id)->toBe($occurrence->getKey())
        ->and($participants[0]->id)->not->toBe($registration->getKey())
        ->and($participants[1]->event_occurrence_id)->toBe($occurrence->getKey())
        ->and($participants[1]->event_session_id)->toBeNull();
});

it('denies participant scope from another event or a mismatched session occurrence', function (): void {
    $event = Event::factory()->create();
    $foreignEvent = Event::factory()->create();
    $foreignOccurrence = EventOccurrence::factory()->create(['event_id' => $foreignEvent->getKey()]);
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->getKey()]);
    $otherSession = EventSession::factory()->create([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => EventOccurrence::factory()->create(['event_id' => $event->getKey()])->getKey(),
    ]);

    expect(fn () => app(RegistrationServiceInterface::class)->register([
        'event_id' => $event->getKey(),
        'registration_type' => 'individual',
        'status' => 'pending',
        'source' => 'website',
        'total_participants' => 1,
        'participants' => [[
            'name' => 'Foreign',
            'event_occurrence_id' => $foreignOccurrence->getKey(),
        ]],
    ]))->toThrow(InvalidArgumentException::class, 'participant occurrence');

    expect(fn () => app(RegistrationServiceInterface::class)->register([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $occurrence->getKey(),
        'registration_type' => 'individual',
        'status' => 'pending',
        'source' => 'website',
        'total_participants' => 1,
        'participants' => [[
            'name' => 'Mismatched',
            'event_session_id' => $otherSession->getKey(),
        ]],
    ]))->toThrow(InvalidArgumentException::class, 'participant session');
});

it('validates order-item scope linkage before writing a registration', function (): void {
    $event = Event::factory()->create();
    $foreignEvent = Event::factory()->create();
    $foreignOccurrence = EventOccurrence::factory()->create(['event_id' => $foreignEvent->getKey()]);

    expect(fn () => app(RegistrationServiceInterface::class)->createFromOrderItem([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $foreignOccurrence->getKey(),
        'registration_type' => 'individual',
        'quantity' => 1,
    ]))->toThrow(InvalidArgumentException::class, 'occurrence does not belong');

    expect(EventRegistration::query()->where('event_id', $event->getKey())->exists())->toBeFalse();
});
