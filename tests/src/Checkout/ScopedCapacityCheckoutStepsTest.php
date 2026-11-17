<?php

declare(strict_types=1);

use AIArmada\Checkout\Contracts\ScopedCapacityCheckoutProviderInterface;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\CommitScopedCapacityStep;
use AIArmada\Checkout\Steps\ReserveScopedCapacityStep;
use AIArmada\Inventory\Contracts\ScopedCapacityReservationServiceInterface;
use AIArmada\Inventory\Data\ScopedCapacityLine;
use AIArmada\Inventory\InventoryServiceProvider;
use AIArmada\Inventory\Models\CapacityHold;
use Illuminate\Support\Str;

final class TestCapacityCheckoutProvider implements ScopedCapacityCheckoutProviderInterface
{
    public function linesFor(CheckoutSession $session): array
    {
        return [new ScopedCapacityLine('scoped-resource', 'window-2026-12-01', 2)];
    }
}

final class UnwritableCapacityCheckoutSession extends CheckoutSession
{
    public function persistState(array $attributes): bool
    {
        throw new RuntimeException('Session storage unavailable');
    }
}

beforeEach(function (): void {
    // The shared package TestCase does not eagerly boot Inventory. Load its
    // real provider here to exercise the production reservation binding.
    app()->register(InventoryServiceProvider::class);
    app()->instance(ScopedCapacityCheckoutProviderInterface::class, new TestCapacityCheckoutProvider);
    app(ScopedCapacityReservationServiceInterface::class)
        ->setCapacity('scoped-resource', 'window-2026-12-01', 4);
});

it('holds resource capacity during checkout and releases it on compensation', function (): void {
    $capacity = app(ScopedCapacityReservationServiceInterface::class);
    $session = CheckoutSession::forceCreate(['cart_id' => 'capacity-cart-1']);
    $reserve = new ReserveScopedCapacityStep;

    expect($reserve->handle($session)->status)->toBe(StepStatus::Completed)
        ->and($capacity->available('scoped-resource', 'window-2026-12-01'))->toBe(2)
        ->and($reserve->handle($session)->status)->toBe(StepStatus::Completed)
        ->and($capacity->available('scoped-resource', 'window-2026-12-01'))->toBe(2);

    $firstReference = data_get($session->pricing_data, 'scoped_capacity_reservation.reference');

    expect($reserve->compensate($session)->status)->toBe(StepStatus::RolledBack)
        ->and($capacity->available('scoped-resource', 'window-2026-12-01'))->toBe(4)
        ->and($reserve->handle($session)->status)->toBe(StepStatus::Completed)
        ->and(data_get($session->pricing_data, 'scoped_capacity_reservation.reference'))->not->toBe($firstReference)
        ->and($capacity->available('scoped-resource', 'window-2026-12-01'))->toBe(2);
});

it('commits selected capacity to the created order and never cancels it as a temporary hold', function (): void {
    $capacity = app(ScopedCapacityReservationServiceInterface::class);
    $session = CheckoutSession::forceCreate(['cart_id' => 'capacity-cart-2']);
    $reserve = new ReserveScopedCapacityStep;

    expect($reserve->handle($session)->status)->toBe(StepStatus::Completed);

    $orderId = (string) Str::uuid();
    $session->order_id = $orderId;
    $commit = new CommitScopedCapacityStep;

    expect($commit->handle($session)->status)->toBe(StepStatus::Completed)
        ->and($capacity->find(data_get($session->pricing_data, 'scoped_capacity_reservation.reference'))->orderId)->toBe($orderId)
        ->and($capacity->available('scoped-resource', 'window-2026-12-01'))->toBe(2);

    expect($reserve->compensate($session)->status)->toBe(StepStatus::RolledBack)
        ->and($capacity->find(data_get($session->pricing_data, 'scoped_capacity_reservation.reference'))->state)->toBe('committed')
        ->and($capacity->available('scoped-resource', 'window-2026-12-01'))->toBe(2);
});

it('immediately releases a hold when checkout cannot persist its reference', function (): void {
    $capacity = app(ScopedCapacityReservationServiceInterface::class);
    $session = new UnwritableCapacityCheckoutSession;
    $session->setAttribute('id', (string) Str::uuid());

    $result = (new ReserveScopedCapacityStep)->handle($session);

    expect($result->status)->toBe(StepStatus::Failed)
        ->and($result->message)->toContain('persist')
        ->and($capacity->available('scoped-resource', 'window-2026-12-01'))->toBe(4)
        ->and(CapacityHold::query()->count())->toBe(0);
});
