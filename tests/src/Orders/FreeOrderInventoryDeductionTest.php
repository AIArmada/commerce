<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\Fixtures\TestOwner;
use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;
use AIArmada\Inventory\Data\ReservationLine;
use AIArmada\Inventory\Listeners\DeductInventoryFromOrder;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Services\Stock\CheckoutReservationService;
use AIArmada\Orders\Events\InventoryDeductionRequired;
use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Events\OrderProcessingStarted;
use AIArmada\Orders\Exceptions\OrderNotAwaitingPayment;
use AIArmada\Orders\Listeners\DeductInventoryOnPaymentConfirmed;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Services\OrderService;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\OnHold;
use AIArmada\Orders\States\PaymentFailed;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('inventory_test_products');
    Schema::create('inventory_test_products', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

function createFreeTestOrder(
    string $number,
    int $grandTotal = 0,
    array $metadata = [],
    int $paidTotal = 0,
    string $status = Created::class,
): Order {
    $order = new Order;
    $order->forceFill([
        'order_number' => $number,
        'status' => $status,
        'currency' => 'USD',
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
        'paid_total' => $paidTotal,
        'metadata' => $metadata,
    ]);
    $order->save();

    return $order;
}

function captureAfterCommit(): Closure
{
    $captured = null;

    $spy = Mockery::mock(DB::getFacadeRoot())->makePartial();
    $spy->shouldReceive('afterCommit')->once()->with(Mockery::on(
        function (mixed $callback) use (&$captured): bool {
            $captured = $callback;

            return true;
        }
    ));
    DB::swap($spy);

    return function () use (&$captured): void {
        expect($captured)->not->toBeNull();
        $captured();
    };
}

it('moves a free order to processing', function (): void {
    $order = createFreeTestOrder('ORD-FREE-001');

    $result = app(OrderService::class)->confirmFreeOrder($order);

    expect($result->status)->toBeInstanceOf(Processing::class)
        ->and($result->paid_at)->toBeNull()
        ->and(OrderPayment::query()->where('order_id', $order->getKey())->count())->toBe(0);
});

it('rejects orders with a balance due', function (): void {
    $order = createFreeTestOrder('ORD-FREE-002', 1000);

    expect(fn () => app(OrderService::class)->confirmFreeOrder($order))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects fully paid orders', function (): void {
    $order = createFreeTestOrder('ORD-FREE-002A', 1000, [], 1000);

    Event::fake([OrderProcessingStarted::class]);

    expect(fn () => app(OrderService::class)->confirmFreeOrder($order))
        ->toThrow(InvalidArgumentException::class);

    expect($order->fresh()->status)->toBeInstanceOf(Created::class);
    Event::assertNotDispatched(OrderProcessingStarted::class);
});

it('rejects partially paid orders', function (): void {
    $order = createFreeTestOrder('ORD-FREE-002B', 1000, [], 400);

    expect(fn () => app(OrderService::class)->confirmFreeOrder($order))
        ->toThrow(InvalidArgumentException::class);

    expect($order->fresh()->status)->toBeInstanceOf(Created::class);
});

it('rejects processing orders that still owe a balance', function (): void {
    $order = createFreeTestOrder('ORD-FREE-002C', 1000, [], 0, Processing::class);

    $spy = Mockery::mock(DB::getFacadeRoot())->makePartial();
    $spy->shouldReceive('afterCommit')->never();
    DB::swap($spy);

    expect(fn () => app(OrderService::class)->confirmFreeOrder($order))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects processing orders that were paid out of band', function (): void {
    $order = createFreeTestOrder('ORD-FREE-002D', 1000, [], 1000, Processing::class);

    expect(fn () => app(OrderService::class)->confirmFreeOrder($order))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects held orders without clearing the hold', function (): void {
    $order = createFreeTestOrder('ORD-FREE-002E', 0, [], 0, OnHold::class);
    $order->forceFill(['held_at' => now()->toImmutable()])->save();

    expect(fn () => app(OrderService::class)->confirmFreeOrder($order))
        ->toThrow(OrderNotAwaitingPayment::class);

    $fresh = $order->fresh();
    expect($fresh->status)->toBeInstanceOf(OnHold::class)
        ->and($fresh->held_at)->not->toBeNull();
});

it('rejects canceled orders', function (): void {
    $order = createFreeTestOrder('ORD-FREE-002J', 0, [], 0, Canceled::class);

    expect(fn () => app(OrderService::class)->confirmFreeOrder($order))
        ->toThrow(InvalidArgumentException::class, 'current state: Canceled');

    expect($order->fresh()->status)->toBeInstanceOf(Canceled::class);
});

it('rejects failed orders', function (): void {
    $order = createFreeTestOrder('ORD-FREE-002K', 0, [], 0, PaymentFailed::class);

    expect(fn () => app(OrderService::class)->confirmFreeOrder($order))
        ->toThrow(InvalidArgumentException::class, 'current state: PaymentFailed');

    expect($order->fresh()->status)->toBeInstanceOf(PaymentFailed::class);
});

it('confirms free orders awaiting payment', function (): void {
    $order = createFreeTestOrder('ORD-FREE-002L', 0, [], 0, PendingPayment::class);

    $result = app(OrderService::class)->confirmFreeOrder($order);

    expect($result->status)->toBeInstanceOf(Processing::class);
});

it('accepts over-discounted orders with nothing paid', function (): void {
    $order = createFreeTestOrder('ORD-FREE-002F', -500);

    $result = app(OrderService::class)->confirmFreeOrder($order);

    expect($result->status)->toBeInstanceOf(Processing::class);
});

it('syncs the caller instance to processing', function (): void {
    $order = createFreeTestOrder('ORD-FREE-002G');

    app(OrderService::class)->confirmFreeOrder($order);

    expect($order->status)->toBeInstanceOf(Processing::class);
});

it('syncs the caller instance even when status was read before confirmation', function (): void {
    $order = createFreeTestOrder('ORD-FREE-002I');

    expect($order->status)->toBeInstanceOf(Created::class);

    app(OrderService::class)->confirmFreeOrder($order);

    expect($order->status)->toBeInstanceOf(Processing::class);
});

it('blocks cross-owner free order confirmation', function (): void {
    config()->set('orders.owner.enabled', false);

    Schema::dropIfExists('test_owners');
    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    $order = createFreeTestOrder('ORD-FREE-002H');
    $order->assignOwner($ownerA);
    $order->save();

    config()->set('orders.owner.enabled', true);

    app()->instance(OwnerResolverInterface::class, new class($ownerB) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    expect(fn () => app(OrderService::class)->confirmFreeOrder($order))
        ->toThrow(RuntimeException::class, 'Cross-owner mutation blocked');
});

it('is idempotent for orders already processing', function (): void {
    $order = createFreeTestOrder('ORD-FREE-003');
    $service = app(OrderService::class);

    $service->confirmFreeOrder($order);

    $spy = Mockery::mock(DB::getFacadeRoot())->makePartial();
    $spy->shouldReceive('afterCommit')->never();
    DB::swap($spy);

    expect($service->confirmFreeOrder($order->fresh())->status)->toBeInstanceOf(Processing::class);
});

it('schedules the processing-started dispatch after commit', function (): void {
    $order = createFreeTestOrder('ORD-FREE-004');

    Event::fake([OrderPaid::class, OrderProcessingStarted::class, OrderFulfillmentRequired::class]);

    $invokeCommitHooks = captureAfterCommit();

    app(OrderService::class)->confirmFreeOrder($order);

    $invokeCommitHooks();

    Event::assertDispatched(
        OrderProcessingStarted::class,
        fn (OrderProcessingStarted $event): bool => $event->order->is($order) && $event->gateway === 'free'
    );
    Event::assertDispatched(
        OrderFulfillmentRequired::class,
        fn (OrderFulfillmentRequired $event): bool => $event->order->is($order) && $event->gateway === 'free'
    );
    Event::assertNotDispatched(OrderPaid::class);
});

it('deducts stock exactly once for a free order end to end', function (): void {
    config()->set('inventory.models.product', InventoryItem::class);
    app()->singleton(CheckoutReservationServiceInterface::class, CheckoutReservationService::class);

    $item = InventoryItem::create(['name' => 'Free Order Item']);
    $location = InventoryLocation::factory()->create();
    $inventoryService = app(InventoryService::class);
    $inventoryService->receive($item, $location->id, 10);

    $order = createFreeTestOrder('ORD-FREE-005', 0, ['cart_id' => 'free-cart-e2e']);

    app(CheckoutReservationServiceInterface::class)->reserve(
        'free-cart-e2e',
        [new ReservationLine(productId: $item->getKey(), quantity: 3)],
        900,
    );

    app(OrderService::class)->confirmFreeOrder($order);

    // Drive the queued deduction chain directly: the suite does not load
    // the provider registrations, and the seam-to-event link is proven by
    // the scheduling test above. Deliver twice: duplicate events must not
    // double-deduct.
    Event::listen(InventoryDeductionRequired::class, DeductInventoryFromOrder::class);
    $listener = app(DeductInventoryOnPaymentConfirmed::class);
    $listener->handle(new OrderProcessingStarted($order, (string) $order->getKey(), 'free'));
    $listener->handle(new OrderProcessingStarted($order, (string) $order->getKey(), 'free'));

    $level = $inventoryService->getLevel($item, $location->id)?->fresh();

    expect($order->fresh()->status)->toBeInstanceOf(Processing::class)
        ->and($level?->quantity_on_hand)->toBe(7)
        ->and($level?->quantity_reserved)->toBe(0)
        ->and(InventoryMovement::query()->where('reference', $order->id)->where('reason', 'sale')->count())->toBe(1);
});
