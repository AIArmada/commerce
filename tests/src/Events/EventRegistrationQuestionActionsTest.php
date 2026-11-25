<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\EventRegistrationQuestionResolver;
use AIArmada\Events\Data\EventRegistrationQuestionStatus;
use AIArmada\Events\Data\EventRegistrationQuestionType;
use AIArmada\Events\Events\EventRegistrationQuestionArchived;
use AIArmada\Events\Events\EventRegistrationQuestionCreated;
use AIArmada\Events\Events\EventRegistrationQuestionUpdated;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Registrations\Actions\ArchiveEventRegistrationQuestionAction;
use AIArmada\Events\Features\Registrations\Actions\CreateEventRegistrationQuestionAction;
use AIArmada\Events\Features\Registrations\Actions\UpdateEventRegistrationQuestionAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistrationQuestion;
use Illuminate\Support\Facades\Event as EventFacade;

it('creates, updates, and archives a question without deleting its definition', function (): void {
    $event = Event::factory()->create();
    EventFacade::fake([
        EventRegistrationQuestionCreated::class,
        EventRegistrationQuestionUpdated::class,
        EventRegistrationQuestionArchived::class,
    ]);

    $questionId = app(CreateEventRegistrationQuestionAction::class)->handle('event', (string) $event->getKey(), [
        'question' => '  Meal   preference  ',
        'type' => EventRegistrationQuestionType::Select->value,
        'options' => ['Halal', 'Vegetarian', 'Halal'],
        'is_required' => true,
    ]);

    $question = EventRegistrationQuestion::query()->findOrFail($questionId);

    expect($question->field_key)->toBe('meal_preference')
        ->and($question->question)->toBe('Meal preference')
        ->and($question->type)->toBe(EventRegistrationQuestionType::Select)
        ->and($question->options)->toBe(['Halal', 'Vegetarian'])
        ->and($question->is_required)->toBeTrue()
        ->and($question->status)->toBe(EventRegistrationQuestionStatus::Active);

    $changes = app(UpdateEventRegistrationQuestionAction::class)->handle((string) $question->getKey(), [
        'question' => 'Meal choice',
        'options' => ['Halal', 'Vegetarian', 'No preference'],
    ]);

    expect($changes)->toHaveKeys(['question', 'options'])
        ->and($question->fresh()->question)->toBe('Meal choice');

    app(ArchiveEventRegistrationQuestionAction::class)->handle((string) $question->getKey());

    expect($question->fresh()->status)->toBe(EventRegistrationQuestionStatus::Archived)
        ->and($question->fresh()->archived_at)->not->toBeNull();

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

    $create->handle('event', (string) $event->getKey(), [
        'field_key' => 'meal_preference',
        'question' => 'Meal preference',
        'type' => EventRegistrationQuestionType::Select->value,
        'options' => ['Halal', 'Vegetarian'],
    ]);
    $create->handle('event', (string) $event->getKey(), [
        'field_key' => 'company',
        'question' => 'Organisation',
    ]);
    $create->handle('occurrence', (string) $occurrence->getKey(), [
        'field_key' => 'meal_preference',
        'question' => 'Meal preference for this day',
        'type' => EventRegistrationQuestionType::Select->value,
        'options' => ['Halal', 'Vegetarian', 'Vegan'],
    ]);
    $sessionQuestionId = $create->handle('session', (string) $session->getKey(), [
        'field_key' => 'accessibility_needs',
        'question' => 'Accessibility needs',
    ]);

    $resolved = collect(app(EventRegistrationQuestionResolver::class)->resolve('session', (string) $session->getKey()));

    expect($resolved)->toHaveCount(3)
        ->and($resolved->firstWhere('fieldKey', 'meal_preference')?->question)
        ->toBe('Meal preference for this day')
        ->and($resolved->firstWhere('fieldKey', 'company'))->not->toBeNull()
        ->and($resolved->firstWhere('fieldKey', 'accessibility_needs')?->id)
        ->toBe($sessionQuestionId);
});

it('requires options for choice questions and prevents duplicate keys in one scope', function (): void {
    $event = Event::factory()->create();
    $create = app(CreateEventRegistrationQuestionAction::class);

    expect(fn () => $create->handle('event', (string) $event->getKey(), [
        'field_key' => 'meal_preference',
        'question' => 'Meal preference',
        'type' => EventRegistrationQuestionType::Select->value,
    ]))->toThrow(InvalidArgumentException::class);

    $create->handle('event', (string) $event->getKey(), [
        'field_key' => 'meal_preference',
        'question' => 'Meal preference',
        'type' => EventRegistrationQuestionType::Select->value,
        'options' => ['Halal'],
    ]);

    expect(fn () => $create->handle('event', (string) $event->getKey(), [
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

    $firstEventQuestionId = $create->handle('event', (string) $firstEvent->getKey(), ['question' => 'First event question']);
    $secondEventQuestionId = $create->handle('event', (string) $secondEvent->getKey(), ['question' => 'Second event question']);
    $occurrenceQuestionId = $create->handle('occurrence', (string) $occurrence->getKey(), ['question' => 'Occurrence question']);
    $sessionQuestionId = $create->handle('session', (string) $session->getKey(), ['question' => 'Session question']);

    expect(EventRegistrationQuestion::query()->findOrFail($firstEventQuestionId)->order_column)->toBe(1)
        ->and(EventRegistrationQuestion::query()->findOrFail($secondEventQuestionId)->order_column)->toBe(1)
        ->and(EventRegistrationQuestion::query()->findOrFail($occurrenceQuestionId)->order_column)->toBe(1)
        ->and(EventRegistrationQuestion::query()->findOrFail($sessionQuestionId)->order_column)->toBe(1);
});
