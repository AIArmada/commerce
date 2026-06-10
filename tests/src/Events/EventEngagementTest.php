<?php

declare(strict_types=1);

namespace Tests\src\Events;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventEngagement;
use AIArmada\Events\Actions\RecordEventEngagementAction;
use Tests\TestCase;

uses(TestCase::class);

it('records a saved engagement', function (): void {
    $event = Event::factory()->create(['status' => 'active']);
    $action = app(RecordEventEngagementAction::class);

    $engagement = $action->handle($event, 'saved', actorType: 'user', actorId: 'user-1');

    expect($engagement->type->value)->toBe('saved');
    expect($engagement->assignable_type)->toBe($event->getMorphClass());
    expect($engagement->assignable_id)->toBe($event->id);
});

it('records a going engagement', function (): void {
    $event = Event::factory()->create(['status' => 'active']);
    $action = app(RecordEventEngagementAction::class);

    $engagement = $action->handle($event, 'going', actorType: 'user', actorId: 'user-1');

    expect($engagement->type->value)->toBe('going');
});

it('records an interested engagement', function (): void {
    $event = Event::factory()->create(['status' => 'active']);
    $action = app(RecordEventEngagementAction::class);

    $engagement = $action->handle($event, 'interested', actorType: 'user', actorId: 'user-1');

    expect($engagement->type->value)->toBe('interested');
});
