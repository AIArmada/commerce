<?php

declare(strict_types=1);

use AIArmada\Events\Models\Event;
use AIArmada\Events\Services\EloquentEventSearchEngine;

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

    expect($results)->toHaveCount(2)
        ->and($results->first()->title)->toBe('Newer');
});
