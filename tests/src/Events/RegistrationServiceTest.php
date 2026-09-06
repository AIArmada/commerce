<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Events\EventRegistrationCompleted;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\States\RegistrationStatus\Pending;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event as EventFacade;

it('creates individual registration', function (): void {
    $event = Event::factory()->create();
    $ticketType = createEventTicketType($event);

    $registration = app(RegistrationServiceInterface::class)->register([
        'event_id' => $event->id,
        'registration_type' => 'individual',
        'status' => 'pending',
        'source' => 'website',
        'total_participants' => 1,
        'participants' => [
            ['name' => 'John Doe', 'is_primary' => true],
        ],
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
        ],
    ]);

    expect($registration->id)->not->toBeNull();
    expect($registration->registration_no)->toStartWith('REG-');
    expect($registration->registered_at)->not->toBeNull();
    expect($registration->participants)->toHaveCount(1);
    expect($registration->items)->toHaveCount(1);
});

it('accepts a registration state object as the initial status', function (): void {
    $event = Event::factory()->create();

    $registration = app(RegistrationServiceInterface::class)->register([
        'event_id' => $event->id,
        'registration_type' => 'individual',
        'status' => new Pending(new EventRegistration),
        'source' => 'website',
        'total_participants' => 1,
    ]);

    expect($registration->status->getValue())->toBe('pending');
});

it('persists answers supplied for each participant', function (): void {
    $event = Event::factory()->create();

    $registration = app(RegistrationServiceInterface::class)->register([
        'event_id' => $event->id,
        'registration_type' => 'individual',
        'status' => 'pending',
        'source' => 'website',
        'total_participants' => 1,
        'participants' => [
            [
                'name' => 'Amina Participant',
                'answers' => [
                    [
                        'field_key' => 'meal_preference',
                        'question' => 'Meal preference',
                        'answer' => 'Vegetarian',
                        'metadata' => ['question_type' => 'select'],
                    ],
                ],
            ],
        ],
    ]);

    $answer = $registration->participants()->firstOrFail()->answers()->first();

    expect($answer)->not->toBeNull()
        ->and($answer?->field_key)->toBe('meal_preference')
        ->and($answer?->question)->toBe('Meal preference')
        ->and($answer?->answer)->toBe('Vegetarian')
        ->and($answer?->event_id)->toBe($event->id)
        ->and($answer?->event_registration_participant_id)->not->toBeNull();
});

it('rolls back the registration when a child record fails', function (): void {
    $event = Event::factory()->create();

    expect(fn () => app(RegistrationServiceInterface::class)->register([
        'event_id' => $event->id,
        'registration_type' => 'individual',
        'status' => 'pending',
        'source' => 'website',
        'total_participants' => 1,
        'answers' => [[]],
    ]))->toThrow(QueryException::class);

    expect(EventRegistration::query()->where('event_id', $event->id)->exists())->toBeFalse();
});

it('cancels registration without deletion', function (): void {
    $event = Event::factory()->create();
    $registration = EventRegistration::factory()->create(['event_id' => $event->id]);

    app(RegistrationServiceInterface::class)->cancel($registration, 'Changed mind');

    expect($registration->fresh()->status->getValue())->toBe('cancelled');
});

it('records the completion timestamp when completing a registration', function (): void {
    $event = Event::factory()->create();
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'status' => 'confirmed',
        'approved_at' => now()->subDay(),
    ]);

    EventFacade::fake([EventRegistrationCompleted::class]);

    app(RegistrationServiceInterface::class)->complete($registration);

    $completed = $registration->fresh();

    expect($completed->status->getValue())->toBe('completed')
        ->and($completed->completed_at)->not->toBeNull();

    EventFacade::assertDispatched(
        EventRegistrationCompleted::class,
        fn (EventRegistrationCompleted $event): bool => $event->registration->is($registration),
    );
});

it('preserves the original lifecycle timestamp when restoring a refund-pending registration', function (): void {
    $event = Event::factory()->create();
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'status' => 'confirmed',
        'approved_at' => now()->subDay(),
    ]);
    $approvedAt = $registration->fresh()->approved_at;

    app(RegistrationServiceInterface::class)->markRefundPending($registration, 'Payment provider retry');
    app(RegistrationServiceInterface::class)->restoreFromRefundPending($registration, 'Payment provider recovered');

    $restored = $registration->fresh();

    expect($restored->status->getValue())->toBe('confirmed')
        ->and($restored->approved_at)->toEqual($approvedAt)
        ->and($restored->refund_pending_at)->toBeNull()
        ->and($restored->last_state_change_at)->not->toBeNull();
});

it('creates order-item registrations with session scope', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
    ]);

    app(RegistrationServiceInterface::class)->createFromOrderItem([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
        'registration_type' => 'individual',
        'quantity' => 1,
    ]);

    $registration = EventRegistration::query()->latest('created_at')->first();

    expect($registration)->not->toBeNull()
        ->and($registration?->event_session_id)->toBe($session->id)
        ->and($registration?->event_occurrence_id)->toBe($occurrence->id);
});
