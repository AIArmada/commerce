<?php

declare(strict_types=1);

namespace Tests\src\Events;

use AIArmada\Events\Actions\RecordEventEngagementAction;
use AIArmada\Events\Models\Event;

it('records a saved engagement', function (): void {
    $event = Event::query()->create(['name' => 'Saved Engagement', 'slug' => 'saved-engagement', 'status' => 'active']);
    $action = app(RecordEventEngagementAction::class);

    $engagement = $action->handle($event, 'saved');

    expect($engagement->type->value)->toBe('saved');
    expect($engagement->actor_type)->toBeNull();
    expect($engagement->actor_id)->toBeNull();
});

it('records a going engagement', function (): void {
    $event = Event::query()->create(['name' => 'Going Engagement', 'slug' => 'going-engagement', 'status' => 'active']);
    $action = app(RecordEventEngagementAction::class);

    $engagement = $action->handle($event, 'going');

    expect($engagement->type->value)->toBe('going');
});

it('records an interested engagement', function (): void {
    $event = Event::query()->create(['name' => 'Interested Engagement', 'slug' => 'interested-engagement', 'status' => 'active']);
    $action = app(RecordEventEngagementAction::class);

    $engagement = $action->handle($event, 'interested');

    expect($engagement->type->value)->toBe('interested');
});
