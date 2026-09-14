<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\Event;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

it('persists bulk children with identical data to per-row creates', function (): void {
    $event = Event::factory()->create();
    $ticketType = createEventTicketType($event);

    $registration = app(RegistrationServiceInterface::class)->register([
        'event_id' => $event->id,
        'registration_type' => 'individual',
        'status' => 'pending',
        'source' => 'website',
        'total_participants' => 1,
        'participants' => [
            [
                'name' => 'Bulk Participant',
                'email' => 'bulk-participant@example.com',
                'answers' => [
                    [
                        'field_key' => 'meal',
                        'question' => 'Meal',
                        'answer' => 'Vegan',
                        'answer_json' => ['option' => 'vegan'],
                        'metadata' => ['question_type' => 'select'],
                    ],
                    [
                        'field_key' => 'tshirt',
                        'question' => 'T-shirt',
                        'answer' => 'M',
                    ],
                ],
            ],
        ],
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 2, 'metadata' => ['seat' => 'A1']],
            ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
        ],
        'answers' => [
            [
                'field_key' => 'notes',
                'question' => 'Notes',
                'answer' => 'None',
                'metadata' => ['source' => 'web'],
            ],
        ],
    ]);

    expect($registration->participants)->toHaveCount(1)
        ->and($registration->items)->toHaveCount(2)
        ->and($registration->answers)->toHaveCount(3)
        ->and($registration->answers()->whereNull('event_registration_participant_id')->count())->toBe(1);

    $participant = $registration->participants()->firstOrFail();

    expect($participant->answers()->count())->toBe(2);

    $participantAnswer = $participant->answers()->where('field_key', 'meal')->firstOrFail();

    expect($participantAnswer->answer_json)->toBe(['option' => 'vegan'])
        ->and($participantAnswer->metadata)->toBe(['question_type' => 'select'])
        ->and($participantAnswer->event_id)->toBe($event->id)
        ->and($participantAnswer->event_registration_id)->toBe($registration->id)
        ->and($participantAnswer->id)->not->toBeNull()
        ->and($participantAnswer->created_at)->not->toBeNull();

    $item = $registration->items()->firstOrFail();

    expect($item->quantity)->toBe(2)
        ->and($item->metadata)->toBe(['seat' => 'A1'])
        ->and($item->event_id)->toBe($event->id)
        ->and($item->event_registration_id)->toBe($registration->id)
        ->and($item->id)->not->toBeNull();

    $answer = $registration->answers()->where('field_key', 'notes')->firstOrFail();

    expect($answer->metadata)->toBe(['source' => 'web'])
        ->and($answer->event_registration_id)->toBe($registration->id);
});

it('inserts bulk children in chunks instead of one query per row', function (): void {
    $event = Event::factory()->create();
    $ticketType = createEventTicketType($event);

    $items = [];

    for ($i = 0; $i < 120; $i++) {
        $items[] = ['ticket_type_id' => $ticketType->id, 'quantity' => 1];
    }

    $answers = [];

    for ($i = 0; $i < 105; $i++) {
        $answers[] = [
            'field_key' => "field-{$i}",
            'question' => "Question {$i}",
            'answer' => "Answer {$i}",
        ];
    }

    $statements = [];

    DB::listen(static function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    $registration = app(RegistrationServiceInterface::class)->register([
        'event_id' => $event->id,
        'registration_type' => 'individual',
        'status' => 'pending',
        'source' => 'website',
        'total_participants' => 1,
        'items' => $items,
        'answers' => $answers,
    ]);

    $itemInserts = array_filter(
        $statements,
        static fn (string $sql): bool => str_contains($sql, 'insert into "event_registration_items"')
    );
    $answerInserts = array_filter(
        $statements,
        static fn (string $sql): bool => str_contains($sql, 'insert into "event_registration_answers"')
    );

    expect($registration->items()->count())->toBe(120)
        ->and($registration->answers()->count())->toBe(105)
        ->and($itemInserts)->toHaveCount(3)
        ->and($answerInserts)->toHaveCount(3);
});

it('checks answer participants with one preloaded query', function (): void {
    $event = Event::factory()->create();

    $answers = [];

    for ($i = 0; $i < 30; $i++) {
        $answers[] = [
            'field_key' => "field-{$i}",
            'question' => "Question {$i}",
            'answer' => "Answer {$i}",
        ];
    }

    $statements = [];

    DB::listen(static function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    $registration = app(RegistrationServiceInterface::class)->register([
        'event_id' => $event->id,
        'registration_type' => 'individual',
        'status' => 'pending',
        'source' => 'website',
        'total_participants' => 1,
        'participants' => [
            ['name' => 'Only Participant'],
        ],
        'answers' => $answers,
    ]);

    $participantSelects = array_filter(
        $statements,
        static fn (string $sql): bool => str_contains($sql, 'event_registration_participants') && str_contains($sql, 'select')
    );

    expect($registration->answers()->count())->toBe(30)
        ->and($participantSelects)->toHaveCount(1);
});

it('rejects answers linked to another registration participant', function (): void {
    $event = Event::factory()->create();

    $other = app(RegistrationServiceInterface::class)->register([
        'event_id' => $event->id,
        'registration_type' => 'individual',
        'status' => 'pending',
        'source' => 'website',
        'total_participants' => 1,
        'participants' => [
            ['name' => 'Foreign Participant'],
        ],
    ]);

    $foreignId = $other->participants()->firstOrFail()->getKey();

    expect(fn () => app(RegistrationServiceInterface::class)->register([
        'event_id' => $event->id,
        'registration_type' => 'individual',
        'status' => 'pending',
        'source' => 'website',
        'total_participants' => 1,
        'participants' => [
            ['name' => 'Own Participant'],
        ],
        'answers' => [
            [
                'field_key' => 'linked',
                'question' => 'Linked',
                'answer' => 'No',
                'event_registration_participant_id' => $foreignId,
            ],
        ],
    ]))->toThrow(InvalidArgumentException::class, 'The selected answer participant does not belong to this registration.');
});
