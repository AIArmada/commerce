<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\CloneEventAction;
use AIArmada\Events\Actions\CloneEventOccurrenceAction;
use AIArmada\Events\Actions\CloneEventSessionAction;
use AIArmada\Events\Actions\SaveEventTemplateAction;
use AIArmada\Events\Contracts\EventCloneService;
use AIArmada\Events\Contracts\EventTemplateService;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventLocation;
use AIArmada\Events\Models\EventInvolvement;
use AIArmada\Events\Models\EventMaterial;
use AIArmada\Events\Models\EventTemplate;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('clones a session with child contents', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'title' => 'Original Session',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    $location = EventLocation::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
        'label' => 'Room A',
    ]);

    $clone = app(CloneEventSessionAction::class)->handle($session, [
        'clone_children' => true,
    ]);

    expect($clone)
        ->exists->toBeTrue()
        ->title->toBe('Original Session (Copy)')
        ->event_id->toBe($event->id)
        ->event_occurrence_id->toBe($occurrence->id);

    expect($clone->status->getValue())->toBe('scheduled');

    $clonedLocations = EventLocation::where('event_session_id', $clone->id)->get();
    expect($clonedLocations)->toHaveCount(1);
    expect($clonedLocations->first()->label)->toBe('Room A');
});

it('clones a session without children when clone_children is false', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'title' => 'Session',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    EventLocation::factory()->create([
        'event_id' => $event->id,
        'event_session_id' => $session->id,
        'label' => 'Room A',
    ]);

    $clone = app(CloneEventSessionAction::class)->handle($session, [
        'clone_children' => false,
    ]);

    $clonedLocations = EventLocation::where('event_session_id', $clone->id)->get();
    expect($clonedLocations)->toHaveCount(0);
});

it('clones an occurrence with child sessions and contents', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'title' => 'Launch Day',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 17:00:00'),
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);

    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'title' => 'Keynote',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    $involvement = EventInvolvement::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'role_code' => 'speaker',
    ]);

    $clone = app(CloneEventOccurrenceAction::class)->handle($occurrence, [
        'clone_sessions' => true,
    ]);

    expect($clone)
        ->exists->toBeTrue()
        ->title->toBe('Launch Day (Copy)')
        ->event_id->toBe($event->id);

    expect($clone->status->getValue())->toBe('scheduled');

    $clonedSessions = EventSession::where('event_occurrence_id', $clone->id)->get();
    expect($clonedSessions)->toHaveCount(1);
    expect($clonedSessions->first()->title)->toBe('Keynote (Copy)');

    $clonedInvolvements = EventInvolvement::where('event_occurrence_id', $clone->id)->get();
    expect($clonedInvolvements)->toHaveCount(1);
});

it('clones an occurrence without sessions when clone_sessions is false', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'title' => 'Launch Day',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 17:00:00'),
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);

    EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'title' => 'Keynote',
    ]);

    $clone = app(CloneEventOccurrenceAction::class)->handle($occurrence, [
        'clone_sessions' => false,
    ]);

    $clonedSessions = EventSession::where('event_occurrence_id', $clone->id)->get();
    expect($clonedSessions)->toHaveCount(0);
});

it('clones an event with event-level children', function (): void {
    $event = Event::factory()->create([
        'title' => 'Tech Conference 2026',
    ]);

    $location = EventLocation::factory()->create([
        'event_id' => $event->id,
        'label' => 'Main Hall',
    ]);

    $clone = app(CloneEventAction::class)->handle($event);

    expect($clone)
        ->exists->toBeTrue()
        ->title->toBe('Tech Conference 2026 (Copy)')
        ->slug->not->toBe($event->slug);

    expect($clone->status->getValue())->toBe('draft');

    $clonedLocations = EventLocation::where('event_id', $clone->id)
        ->whereNull('event_occurrence_id')
        ->get();
    expect($clonedLocations)->toHaveCount(1);
    expect($clonedLocations->first()->label)->toBe('Main Hall');
});

it('clones an event with occurrences when clone_occurrences is true', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'title' => 'Day 1',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 17:00:00'),
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);

    EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'title' => 'Session A',
    ]);

    $clone = app(CloneEventAction::class)->handle($event, [
        'clone_occurrences' => true,
    ]);

    $clonedOccurrences = EventOccurrence::where('event_id', $clone->id)->get();
    expect($clonedOccurrences)->toHaveCount(1);
    expect($clonedOccurrences->first()->title)->toBe('Day 1 (Copy)');
});

it('does not clone runtime data (registrations, attendances)', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 17:00:00'),
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);

    EventLocation::factory()->create([
        'event_id' => $event->id,
        'label' => 'Hall',
    ]);

    $clone = app(CloneEventAction::class)->handle($event);

    $clonedLocations = EventLocation::where('event_id', $clone->id)->count();
    expect($clonedLocations)->toBe(1);

    $clonedOccurrences = EventOccurrence::where('event_id', $clone->id)->count();
    expect($clonedOccurrences)->toBe(0);
});

it('resets lifecycle timestamps on cloned objects', function (): void {
    $event = Event::factory()->create([
        'published_at' => CarbonImmutable::parse('2026-06-01'),
        'status' => 'published',
    ]);

    $clone = app(CloneEventAction::class)->handle($event);

    expect($clone->published_at)->toBeNull();
    expect($clone->cancelled_at)->toBeNull();
    expect($clone->completed_at)->toBeNull();
});

it('clones via EventCloneService', function (): void {
    $event = Event::factory()->create();
    EventLocation::factory()->create([
        'event_id' => $event->id,
        'label' => 'Hall',
    ]);

    $clone = app(EventCloneService::class)->cloneEvent($event);

    expect($clone)->exists->toBeTrue();

    $locations = EventLocation::where('event_id', $clone->id)->count();
    expect($locations)->toBe(1);
});

it('clones occurrence via EventCloneService', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'title' => 'Day 1',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 17:00:00'),
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);

    $clone = app(EventCloneService::class)->cloneOccurrence($occurrence, [
        'clone_sessions' => false,
    ]);

    expect($clone)->exists->toBeTrue()
        ->title->toBe('Day 1 (Copy)');
});

it('clones session via EventCloneService', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'title' => 'Workshop',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    $clone = app(EventCloneService::class)->cloneSession($session);

    expect($clone)->exists->toBeTrue()
        ->title->toBe('Workshop (Copy)');
});

it('creates a template from an event via SaveEventTemplateAction', function (): void {
    $event = Event::factory()->create(['title' => 'Conference', 'summary' => 'A great event']);
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'title' => 'Day 1',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 17:00:00'),
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);

    $template = app(SaveEventTemplateAction::class)->createFromEvent($event, [
        'name' => 'Conference Template',
    ]);

    expect($template)
        ->exists->toBeTrue()
        ->name->toBe('Conference Template')
        ->template_type->toBe('event')
        ->templateable_id->toBe($event->id);

    expect($template->payload)->toHaveKey('title', 'Conference');
    expect($template->items)->toHaveCount(1);
    expect($template->items->first()->item_type)->toBe('occurrence');
});

it('creates an event from a template via EventTemplateService', function (): void {
    $event = Event::factory()->create(['title' => 'Source Event']);
    $template = app(SaveEventTemplateAction::class)->createFromEvent($event, [
        'name' => 'Test Template',
    ]);

    $created = app(EventTemplateService::class)->createFromTemplate($template);

    expect($created)->toBeInstanceOf(Event::class);
    expect($created->exists)->toBeTrue();
    expect($created->title)->toBe('Source Event');
});

it('creates an occurrence from a template via EventTemplateService', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'title' => 'Original Occurrence',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 17:00:00'),
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);

    $template = app(SaveEventTemplateAction::class)->createFromOccurrence($occurrence, [
        'name' => 'Occurrence Template',
    ]);

    $created = app(EventTemplateService::class)->createFromTemplate($template, [
        'event_id' => $event->id,
    ]);

    expect($created)->toBeInstanceOf(EventOccurrence::class);
    expect($created->exists)->toBeTrue();
    expect($created->title)->toBe('Original Occurrence');
    expect($created->event_id)->toBe($event->id);
});

it('creates a session from a template via EventTemplateService', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'title' => 'Workshop',
        'summary' => 'Hands-on session',
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    $template = app(SaveEventTemplateAction::class)->createFromSession($session, [
        'name' => 'Session Template',
    ]);

    $created = app(EventTemplateService::class)->createFromTemplate($template, [
        'event_occurrence_id' => $occurrence->id,
    ]);

    expect($created)->toBeInstanceOf(EventSession::class);
    expect($created->exists)->toBeTrue();
    expect($created->title)->toBe('Workshop');
    expect($created->summary)->toBe('Hands-on session');
});

it('rejects cross-tenant event writes when cloning', function (): void {
    $originalOwnerEnabled = config('events.features.owner.enabled');

    config()->set('events.features.owner.enabled', true);
    config()->set('events.features.owner.include_global', false);
    config()->set('events.features.owner.auto_assign_on_create', true);

    try {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $event = OwnerContext::withOwner($ownerA, fn (): Event => Event::factory()->create());

        expect(function () use ($ownerB, $event): void {
            OwnerContext::withOwner($ownerB, function () use ($event): void {
                app(CloneEventAction::class)->handle($event);
            });
        })->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
    } finally {
        config()->set('events.features.owner.enabled', $originalOwnerEnabled);
    }
});
