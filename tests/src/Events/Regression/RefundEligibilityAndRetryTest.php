<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventOrderDriver;
use AIArmada\Events\Contracts\EventRefundEligibility;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Data\EventRefundResultData;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Queries\EloquentCatalogApi;
use AIArmada\Events\Features\Commerce\Actions\ProcessEventRegistrationRefundAction;
use AIArmada\Events\Features\Commerce\Actions\SyncEventOrderRegistrationsAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\Queries\EloquentRegistrationsApi;
use AIArmada\Events\Platform\CommerceIntegration;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('derives the event-level refund deadline from the primary occurrence', function (): void {
    $startsAt = CarbonImmutable::now()->addDays(10)->startOfHour();
    $event = Event::factory()->create([
        'metadata' => ['registration' => ['refunds_enabled' => true]],
    ]);
    EventOccurrence::factory()->create([
        'event_id' => $event->getKey(),
        'starts_at' => $startsAt,
    ]);
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'status' => 'confirmed',
        'total_amount' => 5_000,
    ]);

    $deadline = app(EventRefundEligibility::class)->selfServiceRefundDeadline(
        (string) $event->getKey(),
        (string) $registration->getKey(),
    );

    expect($deadline)->not->toBeNull()
        ->and($deadline?->toDateTimeString())->toBe($startsAt->subHours(48)->toDateTimeString());
});

it('does not rewrite payment state when a refund failure is not a genuine restoration', function (): void {
    $event = Event::factory()->create();
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'status' => 'confirmed',
        'payment_status' => null,
        'external_order_id' => 'order-not-refunding',
        'external_order_type' => 'order',
    ]);

    app(SyncEventOrderRegistrationsAction::class)->handle('order-not-refunding', 'order', 'refund_failed');

    expect($registration->fresh()?->status->getValue())->toBe('confirmed')
        ->and($registration->fresh()?->payment_status)->toBeNull();
});

it('restores payments only for registrations that were genuinely refund pending', function (): void {
    $event = Event::factory()->create();
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'status' => 'refund_pending',
        'payment_status' => null,
        'external_order_id' => 'order-refunding',
        'external_order_type' => 'order',
        'metadata' => ['refund' => ['original_status' => 'confirmed']],
    ]);

    app(SyncEventOrderRegistrationsAction::class)->handle('order-refunding', 'order', 'refund_failed');

    expect($registration->fresh()?->status->getValue())->toBe('confirmed')
        ->and($registration->fresh()?->payment_status)->toBe('paid');
});

it('runs tenant-owned refund transitions inside the verified owner context', function (): void {
    if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
        $this->markTestSkipped('Orders integration is not available.');
    }

    config()->set('events.features.owner.enabled', true);
    config()->set('events.features.owner.include_global', false);

    $owner = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($owner): void {
        $event = Event::factory()->create();
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->getKey(),
            'status' => 'confirmed',
            'total_amount' => 5_000,
            'external_order_id' => 'order-tenant-refund',
            'external_order_type' => 'order',
        ]);

        $orders = Mockery::mock(EventOrderDriver::class);
        $orders->shouldReceive('isPaidOrder')->once()->with('order-tenant-refund')->andReturnTrue();
        $orders->shouldReceive('refundIdForRegistration')->once()->andReturnNull();
        $orders->shouldReceive('createRefund')->once()->andReturn('refund-tenant-1');
        $orders->shouldReceive('claimRefund')->once()->with('refund-tenant-1')->andReturnTrue();
        $orders->shouldReceive('correlateRefundProviderReference')->once();
        $orders->shouldReceive('submitRefund')->once()->with('refund-tenant-1')->andReturn(
            new EventRefundResultData(status: 'pending'),
        );

        $eligibility = Mockery::mock(EventRefundEligibility::class);
        $eligibility->shouldReceive('selfServiceRefundReason')->once()->andReturnNull();

        $observedOwner = null;

        $registrations = Mockery::mock(RegistrationServiceInterface::class);
        $registrations->shouldReceive('markRefundPending')->once()
            ->andReturnUsing(function (string $registrationId, string $reason) use (&$observedOwner): void {
                $observedOwner = OwnerContext::resolve();
            });

        $action = new ProcessEventRegistrationRefundAction(
            orders: $orders,
            eligibility: $eligibility,
            registrations: $registrations,
            registrationsApi: app(EloquentRegistrationsApi::class),
            catalog: app(EloquentCatalogApi::class),
        );

        $result = $action->handle(
            eventId: (string) $event->getKey(),
            registrationId: (string) $registration->getKey(),
            actor: $owner,
            authorize: false,
        );

        expect($result->status)->toBe('pending')
            ->and($observedOwner)->not->toBeNull()
            ->and($observedOwner?->is($owner))->toBeTrue();
    });
});
