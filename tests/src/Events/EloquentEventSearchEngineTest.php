<?php

declare(strict_types=1);

use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Search\Queries\EloquentEventSearchEngine;

it('falls back to safe search sorting for unsupported fields and directions', function (): void {
    Event::factory()->create([
        'title' => 'Older',
        'created_at' => now()->subDay(),
    ]);
    Event::factory()->create([
        'title' => 'Newer',
        'created_at' => now(),
    ]);

    $results = app(EloquentEventSearchEngine::class)->search([
        'sort' => 'owner_id',
        'sort_dir' => 'sideways',
    ]);

    /** @var Event|null $first */
    $first = $results->first();

    expect($results)->toHaveCount(2)
        ->and($first?->title)->toBe('Newer');
});

it('excludes hidden events from discovery search unless explicitly requested', function (): void {
    $hidden = Event::factory()->create(['visibility' => 'hidden']);
    $public = Event::factory()->create(['visibility' => 'public']);

    $results = app(EloquentEventSearchEngine::class)->search([]);

    expect($results->pluck('id'))->toContain($public->id)
        ->and($results->pluck('id'))->not->toContain($hidden->id);

    $hiddenResults = app(EloquentEventSearchEngine::class)->search(['visibility' => 'hidden']);

    expect($hiddenResults->pluck('id'))->toContain($hidden->id);
});
