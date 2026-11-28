<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventCloneService;
use AIArmada\Events\Contracts\EventTemplateService;
use AIArmada\Events\Features\Catalog\Actions\CloneEventAction;
use AIArmada\Events\Features\Catalog\Actions\CloneEventContentsAction;
use AIArmada\Events\Features\Catalog\Actions\CloneEventOccurrenceAction;
use AIArmada\Events\Features\Catalog\Actions\CloneEventSessionAction;
use AIArmada\Events\Features\Catalog\Actions\SaveEventTemplateAction;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventInvolvement;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Venues\Models\EventLocation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

final class CloneConfiguredEvent extends Event {}

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

    EventInvolvement::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
        'role_code' => 'speaker',
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

    $clonedInvolvements = EventInvolvement::where('event_session_id', $clone->id)->get();
    expect($clonedInvolvements)->toHaveCount(1);
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

    EventInvolvement::factory()->create([
        'event_id' => $event->id,
        'event_session_id' => $session->id,
        'role_code' => 'speaker',
    ]);

    $clone = app(CloneEventSessionAction::class)->handle($session, [
        'clone_children' => false,
    ]);

    $clonedInvolvements = EventInvolvement::where('event_session_id', $clone->id)->get();
    expect($clonedInvolvements)->toHaveCount(0);
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

it('clones Spatie media for occurrences and sessions', function (): void {
    Storage::fake('public');
    config()->set('media-library.file_namer', DefaultFileNamer::class);
    config()->set('media-library.image_optimizers', []);
    config()->set('media-library.max_file_size', 10 * 1024 * 1024);
    config()->set('media-library.path_generator', DefaultPathGenerator::class);
    config()->set('events.media.profiles.occurrence.collections.cover.responsive', false);
    config()->set('events.media.profiles.occurrence.conversions', []);
    config()->set('events.media.profiles.session.collections.cover.responsive', false);
    config()->set('events.media.profiles.session.conversions', []);

    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'starts_at' => CarbonImmutable::parse('2026-07-01 08:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 17:00:00'),
        'timezone' => 'UTC',
        'delivery_mode' => 'in_person',
    ]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'starts_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-07-01 10:00:00'),
    ]);

    $occurrenceCover = UploadedFile::fake()->image('occurrence-cover.jpg');
    $sessionCover = UploadedFile::fake()->image('session-cover.jpg');

    $occurrence->addMedia($occurrenceCover)
        ->toMediaCollection('cover');
    $session->addMedia($sessionCover)
        ->toMediaCollection('cover');

    expect($occurrence->getMedia('cover'))->toHaveCount(1)
        ->and($occurrence->media()->count())->toBe(1)
        ->and($session->getMedia('cover'))->toHaveCount(1);

    $clone = app(CloneEventOccurrenceAction::class)->handle($occurrence);
    $clonedSession = $clone->sessions()->firstOrFail();

    expect($clone->getMedia('cover'))->toHaveCount(1)
        ->and($clonedSession->getMedia('cover'))->toHaveCount(1);
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

    EventInvolvement::factory()->create([
        'event_id' => $event->id,
        'role_code' => 'speaker',
    ]);

    $clone = app(CloneEventAction::class)->handle($event);

    expect($clone)
        ->exists->toBeTrue()
        ->title->toBe('Tech Conference 2026 (Copy)')
        ->slug->not->toBe($event->slug);

    expect($clone->status->getValue())->toBe('draft');

    $clonedInvolvements = EventInvolvement::where('event_id', $clone->id)
        ->whereNull('event_occurrence_id')
        ->get();
    expect($clonedInvolvements)->toHaveCount(1);
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

    EventInvolvement::factory()->create([
        'event_id' => $event->id,
        'role_code' => 'speaker',
    ]);

    $clone = app(CloneEventAction::class)->handle($event);

    $clonedInvolvements = EventInvolvement::where('event_id', $clone->id)->count();
    expect($clonedInvolvements)->toBe(1);

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

    $cloneId = app(EventCloneService::class)->cloneEvent((string) $event->getKey());
    $clone = Event::query()->findOrFail($cloneId);

    expect($clone->exists)->toBeTrue();

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

    $cloneId = app(EventCloneService::class)->cloneOccurrence((string) $occurrence->getKey(), [
        'clone_sessions' => false,
    ]);

    $clone = EventOccurrence::query()->findOrFail($cloneId);

    expect($clone->exists)->toBeTrue()
        ->and($clone->title)->toBe('Day 1 (Copy)');
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

    $cloneId = app(EventCloneService::class)->cloneSession((string) $session->getKey());
    $clone = EventSession::query()->findOrFail($cloneId);

    expect($clone->exists)->toBeTrue()
        ->and($clone->title)->toBe('Workshop (Copy)');
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

    $createdId = app(EventTemplateService::class)->createFromTemplate((string) $template->getKey());
    $created = Event::query()->findOrFail($createdId);

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

    $createdId = app(EventTemplateService::class)->createFromTemplate((string) $template->getKey(), [
        'event_id' => (string) $event->getKey(),
    ]);

    $created = EventOccurrence::query()->findOrFail($createdId);

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

    $createdId = app(EventTemplateService::class)->createFromTemplate((string) $template->getKey(), [
        'event_occurrence_id' => (string) $occurrence->getKey(),
    ]);

    $created = EventSession::query()->findOrFail($createdId);

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
        })->toThrow(AuthorizationException::class);
    } finally {
        config()->set('events.features.owner.enabled', $originalOwnerEnabled);
    }
});

it('rejects cloning a session into an occurrence belonging to another event', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $session = EventSession::factory()->create(['event_id' => $event->id, 'event_occurrence_id' => $occurrence->id]);
    $otherEvent = Event::factory()->create();

    expect(fn () => app(CloneEventSessionAction::class)->handle($session, [
        'event_id' => $otherEvent->id,
        'clone_children' => false,
    ]))->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(EventSession::query()->where('event_id', $otherEvent->id)->exists())->toBeFalse();
});

it('preserves the unknown end time when cloning an open-ended occurrence', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'starts_at' => CarbonImmutable::parse('2027-01-01 09:00:00'),
        'ends_at' => null,
    ]);

    $clone = app(CloneEventOccurrenceAction::class)->handle($occurrence, ['clone_sessions' => false]);

    expect($clone->ends_at)->toBeNull();
});

it('rejects invalid time overrides when cloning a session', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
    $session = EventSession::factory()->create(['event_id' => $event->id, 'event_occurrence_id' => $occurrence->id]);

    expect(fn () => app(CloneEventSessionAction::class)->handle($session, [
        'starts_at' => '2027-01-01 10:00:00',
        'ends_at' => '2027-01-01 09:00:00',
    ]))->toThrow(InvalidArgumentException::class);
});

it('rolls back the cloned event if a scope extension fails', function (): void {
    $event = Event::factory()->create();
    $cloner = Mockery::mock(AIArmada\Events\Contracts\EventScopeCloner::class);
    $cloner->shouldReceive('cloneForScope')->once()->andThrow(new RuntimeException('Extension failed'));
    app()->instance(AIArmada\Events\Contracts\EventScopeCloner::class, $cloner);

    $registry = new AIArmada\Events\Platform\ExtensionRegistry(app(), [
        'events.clone-scope' => [AIArmada\Events\Contracts\EventScopeCloner::class],
    ]);
    $service = new AIArmada\Events\Features\Catalog\Actions\DefaultEventCloneService(
        app(CloneEventAction::class),
        app(CloneEventOccurrenceAction::class),
        app(CloneEventSessionAction::class),
        $registry,
    );

    expect(fn () => $service->cloneEvent($event->id, ['title' => 'Failed clone']))
        ->toThrow(RuntimeException::class, 'Extension failed');

    expect(Event::query()->where('title', 'Failed clone')->exists())->toBeFalse();
});

it('rejects cloning content into the source scope', function (): void {
    $event = Event::factory()->create();

    expect(fn () => app(CloneEventContentsAction::class)->handle($event->id, $event->id))
        ->toThrow(InvalidArgumentException::class, 'Cannot clone contents into the source scope.');
});

it('rejects mismatched occurrence scopes before copying contents', function (): void {
    $event = Event::factory()->create();
    $other = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    expect(fn () => app(CloneEventContentsAction::class)->handle(
        $event->id,
        $other->id,
        sourceOccurrenceId: $occurrence->id,
        targetOccurrenceId: $occurrence->id,
    ))->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('uses the configured event model when cloning', function (): void {
    $event = Event::factory()->create();
    config()->set('events.models.event', CloneConfiguredEvent::class);

    $clone = app(CloneEventAction::class)->handle($event);

    expect($clone)->toBeInstanceOf(CloneConfiguredEvent::class)
        ->and($clone->id)->not->toBe($event->id);

    $id = app(EventCloneService::class)->cloneEvent($event->id);

    expect(CloneConfiguredEvent::query()->findOrFail($id)->id)->not->toBe($event->id);
});

it('restores each event template session into its original occurrence', function (): void {
    $event = Event::factory()->create();
    $first = EventOccurrence::factory()->create(['event_id' => $event->id, 'title' => 'Day One']);
    $second = EventOccurrence::factory()->create(['event_id' => $event->id, 'title' => 'Day Two']);

    EventSession::factory()->create(['event_id' => $event->id, 'event_occurrence_id' => $first->id, 'title' => 'Opening', 'sort_order' => 3]);
    EventSession::factory()->create(['event_id' => $event->id, 'event_occurrence_id' => $second->id, 'title' => 'Closing', 'sort_order' => 7]);

    $template = app(SaveEventTemplateAction::class)->createFromEvent($event);
    $id = app(EventTemplateService::class)->createFromTemplate($template->id);
    $restored = Event::query()->findOrFail($id);
    $dayOne = $restored->occurrences()->where('title', 'Day One')->firstOrFail();
    $dayTwo = $restored->occurrences()->where('title', 'Day Two')->firstOrFail();

    expect($dayOne->sessions()->pluck('title')->all())->toBe(['Opening'])
        ->and($dayTwo->sessions()->pluck('title')->all())->toBe(['Closing'])
        ->and($dayTwo->sessions()->firstOrFail()->sort_order)->toBe(7);
});

it('restores an event template using the configured event model', function (): void {
    $event = Event::factory()->create();
    EventOccurrence::factory()->create(['event_id' => $event->id]);
    $template = app(SaveEventTemplateAction::class)->createFromEvent($event);
    config()->set('events.models.event', CloneConfiguredEvent::class);

    $id = app(EventTemplateService::class)->createFromTemplate($template->id);
    $restored = CloneConfiguredEvent::query()->findOrFail($id);

    expect($restored->occurrences()->count())->toBe(1);
});
