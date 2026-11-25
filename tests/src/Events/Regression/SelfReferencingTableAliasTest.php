<?php

declare(strict_types=1);

use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Taxonomy\Models\EventTerm;
use AIArmada\Events\Features\Venues\Models\Venue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

it('honours per-instance table overrides for self-referencing models', function (): void {
    $models = [
        EventOccurrence::class,
        Venue::class,
        EventTerm::class,
        EventRegistration::class,
    ];

    foreach ($models as $modelClass) {
        $alias = 'alias_' . Str::snake(class_basename($modelClass));

        /** @var Model $model */
        $model = new $modelClass;
        $model->setTable($alias);

        expect($model->getTable())->toBe($alias);
    }
});

it('eager loads ticket types through the resolved-modes scope', function (): void {
    config()->set('events.features.owner.enabled', false);

    $event = Event::factory()->create();

    $loaded = Event::query()
        ->whereKey($event->getKey())
        ->withResolvedModes()
        ->firstOrFail();

    expect($loaded->relationLoaded('ticketTypes'))->toBeTrue();
});
