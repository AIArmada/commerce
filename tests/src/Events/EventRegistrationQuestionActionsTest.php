<?php

declare(strict_types=1);

use AIArmada\Events\Actions\ArchiveEventRegistrationQuestionAction;
use AIArmada\Events\Actions\CreateEventRegistrationQuestionAction;
use AIArmada\Events\Actions\UpdateEventRegistrationQuestionAction;
use AIArmada\Events\Contracts\EventRegistrationQuestionResolver;
use AIArmada\Events\Enums\EventRegistrationQuestionStatus;
use AIArmada\Events\Enums\EventRegistrationQuestionType;
use AIArmada\Events\Events\EventRegistrationQuestionArchived;
use AIArmada\Events\Events\EventRegistrationQuestionCreated;
use AIArmada\Events\Events\EventRegistrationQuestionUpdated;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use Illuminate\Support\Facades\Event as EventFacade;

it('creates, updates, and archives a question without deleting its definition', function (): void {
    $event = Event::factory()->create();
    EventFacade::fake([
        EventRegistrationQuestionCreated::class,
        EventRegistrationQuestionUpdated::class,
        EventRegistrationQuestionArchived::class,
    ]);

    $question = app(CreateEventRegistrationQuestionAction::class)->handle($event, [
        'question' => '  Meal   preference  ',
        'type' => EventRegistrationQuestionType::Select->value,
        'options' => ['Halal', 'Vegetarian', 'Halal'],
        'is_required' => true,
    ]);

    expect($question->field_key)->toBe('meal_preference')
        ->and($question->question)->toBe('Meal preference')
        ->and($question->type)->toBe(EventRegistrationQuestionType::Select)
        ->and($question->options)->toBe(['Halal', 'Vegetarian'])
        ->and($question->is_required)->toBeTrue()
        ->and($question->status)->toBe(EventRegistrationQuestionStatus::Active);

    $updated = app(UpdateEventRegistrationQuestionAction::class)->handle($question, [
        'question' => 'Meal choice',
        'options' => ['Halal', 'Vegetarian', 'No preference'],
    ]);

    expect($updated['changes'])->toHaveKeys(['question', 'options'])
        ->and($updated['question']->fresh()->question)->toBe('Meal choice');

    $archived = app(ArchiveEventRegistrationQuestionAction::class)->handle($question);

    expect($archived->getKey())->toBe($question->getKey())
        ->and($archived->fresh()->status)->toBe(EventRegistrationQuestionStatus::Archived)
        ->and($archived->fresh()->archived_at)->not->toBeNull();

    EventFacade::assertDispatched(EventRegistrationQuestionCreated::class);
    EventFacade::assertDispatched(EventRegistrationQuestionUpdated::class);
    EventFacade::assertDispatched(EventRegistrationQuestionArchived::class);
});

it('resolves inherited questions and lets narrower scopes replace a field key', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
    ]);

    $create = app(CreateEventRegistrationQuestionAction::class);

    $create->handle($event, [
        'field_key' => 'meal_preference',
        'question' => 'Meal preference',
        'type' => EventRegistrationQuestionType::Select->value,
        'options' => ['Halal', 'Vegetarian'],
    ]);
    $create->handle($event, [
        'field_key' => 'company',
        'question' => 'Organisation',
    ]);
    $create->handle($occurrence, [
        'field_key' => 'meal_preference',
        'question' => 'Meal preference for this day',
        'type' => EventRegistrationQuestionType::Select->value,
        'options' => ['Halal', 'Vegetarian', 'Vegan'],
    ]);
    $sessionQuestion = $create->handle($session, [
        'field_key' => 'accessibility_needs',
        'question' => 'Accessibility needs',
    ]);

    $resolved = app(EventRegistrationQuestionResolver::class)->resolve($session);

    expect($resolved)->toHaveCount(3)
        ->and($resolved->firstWhere('field_key', 'meal_preference')?->question)
        ->toBe('Meal preference for this day')
        ->and($resolved->firstWhere('field_key', 'company'))->not->toBeNull()
        ->and($resolved->firstWhere('field_key', 'accessibility_needs')?->getKey())
        ->toBe($sessionQuestion->getKey());
});

it('requires options for choice questions and prevents duplicate keys in one scope', function (): void {
    $event = Event::factory()->create();
    $create = app(CreateEventRegistrationQuestionAction::class);

    expect(fn () => $create->handle($event, [
        'field_key' => 'meal_preference',
        'question' => 'Meal preference',
        'type' => EventRegistrationQuestionType::Select->value,
    ]))->toThrow(InvalidArgumentException::class);

    $create->handle($event, [
        'field_key' => 'meal_preference',
        'question' => 'Meal preference',
        'type' => EventRegistrationQuestionType::Select->value,
        'options' => ['Halal'],
    ]);

    expect(fn () => $create->handle($event, [
        'field_key' => 'meal_preference',
        'question' => 'Another meal preference',
        'type' => EventRegistrationQuestionType::Select->value,
        'options' => ['Vegetarian'],
    ]))->toThrow(InvalidArgumentException::class);
});

it('starts question ordering independently for each scope', function (): void {
    $firstEvent = Event::factory()->create();
    $secondEvent = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $firstEvent->id]);
    $session = EventSession::factory()->create([
        'event_id' => $firstEvent->id,
        'event_occurrence_id' => $occurrence->id,
    ]);

    $create = app(CreateEventRegistrationQuestionAction::class);

    $firstEventQuestion = $create->handle($firstEvent, ['question' => 'First event question']);
    $secondEventQuestion = $create->handle($secondEvent, ['question' => 'Second event question']);
    $occurrenceQuestion = $create->handle($occurrence, ['question' => 'Occurrence question']);
    $sessionQuestion = $create->handle($session, ['question' => 'Session question']);

    expect($firstEventQuestion->order_column)->toBe(1)
        ->and($secondEventQuestion->order_column)->toBe(1)
        ->and($occurrenceQuestion->order_column)->toBe(1)
        ->and($sessionQuestion->order_column)->toBe(1);
});
