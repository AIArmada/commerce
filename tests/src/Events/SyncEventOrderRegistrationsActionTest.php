<?php

declare(strict_types=1);

use AIArmada\Events\Actions\SyncEventOrderRegistrationsAction;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventRegistration;

it('handles repeated order lifecycle events idempotently', function (): void {
    $event = Event::factory()->create();
    $paid = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'status' => 'pending',
        'external_order_id' => 'order-paid',
        'external_order_type' => 'order',
    ]);
    $cancelled = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'status' => 'pending',
        'external_order_id' => 'order-cancelled',
        'external_order_type' => 'order',
    ]);
    $refunded = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'status' => 'confirmed',
        'external_order_id' => 'order-refunded',
        'external_order_type' => 'order',
    ]);

    $action = app(SyncEventOrderRegistrationsAction::class);

    expect($action->handle('order-paid', 'order', 'paid'))->toBe(1)
        ->and($action->handle('order-paid', 'order', 'paid'))->toBe(1)
        ->and($action->handle('order-cancelled', 'order', 'cancelled'))->toBe(1)
        ->and($action->handle('order-cancelled', 'order', 'cancelled'))->toBe(1)
        ->and($action->handle('order-refunded', 'order', 'refunded'))->toBe(1)
        ->and($action->handle('order-refunded', 'order', 'refunded'))->toBe(1);

    expect($paid->fresh()->status->getValue())->toBe('confirmed')
        ->and($cancelled->fresh()->status->getValue())->toBe('cancelled')
        ->and($refunded->fresh()->status->getValue())->toBe('refunded')
        ->and($refunded->fresh()->refunded_at)->not->toBeNull();
});

it('rejects unsupported order event types', function (): void {
    expect(fn () => app(SyncEventOrderRegistrationsAction::class)->handle('order', 'order', 'unknown'))
        ->toThrow(InvalidArgumentException::class);
});
