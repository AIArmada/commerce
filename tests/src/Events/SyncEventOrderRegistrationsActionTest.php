<?php

declare(strict_types=1);

use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Commerce\Actions\SyncEventOrderRegistrationsAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;

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

it('keeps free registrations free on fulfillment sync', function (): void {
    $event = Event::factory()->create();
    $free = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'status' => 'pending',
        'payment_status' => 'free',
        'external_order_id' => 'order-free',
        'external_order_type' => 'order',
    ]);

    $action = app(SyncEventOrderRegistrationsAction::class);

    expect($action->handle('order-free', 'order', 'free'))->toBe(1)
        ->and($action->handle('order-free', 'order', 'free'))->toBe(1);

    expect($free->fresh()->status->getValue())->toBe('confirmed')
        ->and($free->fresh()->payment_status)->toBe('free');
});

it('rejects unsupported order event types', function (): void {
    expect(fn () => app(SyncEventOrderRegistrationsAction::class)->handle('order', 'order', 'unknown'))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps cancellation and refund transitions and payment state in one terminal update', function (): void {
    $event = Event::factory()->create();
    $cancelled = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'status' => 'confirmed',
        'external_order_id' => 'order-cancel-terminal',
        'external_order_type' => 'order',
        'payment_status' => 'paid',
    ]);
    $refunded = EventRegistration::factory()->create([
        'event_id' => $event->id,
        'status' => 'refund_pending',
        'external_order_id' => 'order-refund-terminal',
        'external_order_type' => 'order',
        'payment_status' => 'paid',
        'metadata' => ['refund' => ['original_status' => 'confirmed']],
    ]);

    $action = app(SyncEventOrderRegistrationsAction::class);

    $action->handle('order-cancel-terminal', 'order', 'cancelled');
    $action->handle('order-cancel-terminal', 'order', 'cancelled');
    $action->handle('order-refund-terminal', 'order', 'refunded');
    $action->handle('order-refund-terminal', 'order', 'refunded');

    expect($cancelled->fresh()->status->getValue())->toBe('cancelled')
        ->and($cancelled->fresh()->cancelled_at)->not->toBeNull()
        ->and($cancelled->fresh()->payment_status)->toBe('cancelled')
        ->and($refunded->fresh()->status->getValue())->toBe('refunded')
        ->and($refunded->fresh()->refunded_at)->not->toBeNull()
        ->and($refunded->fresh()->payment_status)->toBe('refunded');
});
