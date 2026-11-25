<?php

declare(strict_types=1);

use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Queries\EventQueryService;
use AIArmada\Events\Features\Taxonomy\Actions\SyncEventClassificationsAction;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('keeps the surrounding transaction usable when classification rows already exist', function (): void {
    $event = Event::factory()->create();

    [$first, $second, $after] = DB::transaction(function () use ($event): array {
        $action = app(SyncEventClassificationsAction::class);

        $first = $action->handle($event, ['topic' => ['AI 101']], ['topic' => ['name' => 'Topic']]);
        $second = $action->handle($event, ['topic' => ['AI 101']], ['topic' => ['name' => 'Topic']]);

        $after = Event::factory()->create()->getKey();

        return [$first, $second, $after];
    });

    expect($first)->toBe(1)
        ->and($second)->toBe(1)
        ->and(Event::query()->whereKey($after)->exists())->toBeTrue();
});

it('orders published events by their next upcoming occurrence', function (): void {
    $later = Event::factory()->published()->create();
    $sooner = Event::factory()->published()->create();
    $pastOnly = Event::factory()->published()->create();
    $unpublished = Event::factory()->create();

    EventOccurrence::factory()->create([
        'event_id' => $later->id,
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5)->addHour(),
    ]);
    EventOccurrence::factory()->create([
        'event_id' => $sooner->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);
    EventOccurrence::factory()->create([
        'event_id' => $pastOnly->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
    ]);
    EventOccurrence::factory()->create([
        'event_id' => $unpublished->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $results = app(EventQueryService::class)->findUpcoming(10);

    expect($results->pluck('id')->all())->toBe([$sooner->id, $later->id]);
});
