<?php

declare(strict_types=1);

use AIArmada\Events\Actions\DispatchEventChangeChainAction;
use AIArmada\Events\Models\Event;

it('creates change log with update for cancellations', function (): void {
    $event = Event::factory()->create();

    DispatchEventChangeChainAction::run(
        eventId: $event->id,
        changeType: 'cancelled',
        changeCategory: 'status',
        impactLevel: 'critical',
        requiresNotification: true,
        reason: 'Weather conditions',
    );

    expect($event->fresh()->changeLogs)->toHaveCount(1);
    expect($event->fresh()->updates)->toHaveCount(1);
});

it('creates notification batch for critical changes', function (): void {
    $event = Event::factory()->create();

    DispatchEventChangeChainAction::run(
        eventId: $event->id,
        changeType: 'cancelled',
        changeCategory: 'status',
        impactLevel: 'critical',
        requiresNotification: true,
        reason: 'Weather conditions',
    );

    expect($event->fresh()->notificationBatches)->toHaveCount(1);
});
