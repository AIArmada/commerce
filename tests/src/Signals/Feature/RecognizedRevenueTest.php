<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Orders\Models\Order;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\Recorders\CheckoutSignalRecorder;
use AIArmada\Signals\Services\Recorders\FilamentCartSignalRecorder;
use AIArmada\Signals\Services\Recorders\OrderSignalRecorder;
use AIArmada\Signals\Services\SignalMetricsAggregator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(SignalsTestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('orders');
    Schema::create('orders', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('order_number')->unique();
        $table->string('status', 50)->default('created')->index();
        $table->nullableUuidMorphs('customer');
        $table->nullableUuidMorphs('owner');
        $table->unsignedBigInteger('subtotal')->default(0);
        $table->unsignedBigInteger('discount_total')->default(0);
        $table->unsignedBigInteger('shipping_total')->default(0);
        $table->unsignedBigInteger('tax_total')->default(0);
        $table->unsignedBigInteger('grand_total')->default(0);
        $table->unsignedBigInteger('paid_total')->default(0);
        $table->unsignedBigInteger('refunded_total')->default(0);
        $table->unsignedBigInteger('pending_refunded_total')->default(0);
        $table->string('currency', 3)->default('MYR');
        $table->text('notes')->nullable();
        $table->text('internal_notes')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamp('paid_at')->nullable()->index();
        $table->timestamp('shipped_at')->nullable();
        $table->timestamp('delivered_at')->nullable();
        $table->timestamp('canceled_at')->nullable();
        $table->string('cancellation_reason')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('checkout_sessions');
    Schema::create('checkout_sessions', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('cart_id')->index();
        $table->foreignUuid('customer_id')->nullable()->index();
        $table->foreignUuid('order_id')->nullable()->index();
        $table->string('payment_id')->nullable()->index();
        $table->nullableUuidMorphs('owner');
        $table->string('status')->default('pending')->index();
        $table->string('current_step')->nullable();
        $table->string('error_message')->nullable();
        $table->json('cart_snapshot')->nullable();
        $table->json('step_states')->nullable();
        $table->json('shipping_data')->nullable();
        $table->json('billing_data')->nullable();
        $table->json('pricing_data')->nullable();
        $table->json('discount_data')->nullable();
        $table->json('tax_data')->nullable();
        $table->json('payment_data')->nullable();
        $table->string('payment_redirect_url', 2048)->nullable();
        $table->unsignedSmallInteger('payment_attempts')->default(0);
        $table->string('selected_shipping_method')->nullable();
        $table->string('selected_payment_gateway')->nullable();
        $table->unsignedBigInteger('subtotal')->default(0);
        $table->unsignedBigInteger('discount_total')->default(0);
        $table->unsignedBigInteger('shipping_total')->default(0);
        $table->unsignedBigInteger('tax_total')->default(0);
        $table->unsignedBigInteger('grand_total')->default(0);
        $table->string('currency', 3)->default('MYR');
        $table->timestamp('expires_at')->nullable()->index();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });
});

function revenueTestProperty(): TrackedProperty
{
    return TrackedProperty::query()->create([
        'name' => 'Revenue Property',
        'slug' => 'revenue-property-' . Str::lower(Str::random(6)),
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);
}

function revenueTestSession(User $owner, int $grandTotal = 10000): CheckoutSession
{
    $session = CheckoutSession::query()->create([
        'cart_id' => 'revenue-cart-' . Str::lower(Str::random(6)),
        'customer_id' => $owner->id,
        'status' => 'pending',
    ]);
    $session->forceFill([
        'grand_total' => $grandTotal,
        'currency' => 'MYR',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ])->save();

    return $session;
}

function revenueTestOrder(User $owner, int $grandTotal = 10000): Order
{
    $order = Order::query()->create([
        'order_number' => 'REV-' . Str::upper(Str::random(8)),
        'customer_id' => $owner->id,
        'customer_type' => $owner->getMorphClass(),
        'grand_total' => $grandTotal,
        'currency' => 'MYR',
        'paid_at' => Carbon::now(),
    ]);
    $order->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ])->save();

    return $order;
}

it('does not recognize abandoned checkout value as revenue', function (): void {
    $owner = User::query()->firstOrFail();
    $property = revenueTestProperty();
    $session = revenueTestSession($owner, 10000);

    $event = app(CheckoutSignalRecorder::class)->recordStarted($session);

    expect($event)->not->toBeNull()
        ->and($event->revenue_minor)->toBe(0)
        ->and($event->properties['total_minor'] ?? null)->toBe(10000);

    $metric = app(SignalMetricsAggregator::class)->aggregateForDate(Carbon::now(), $property);

    expect($metric->revenue_minor)->toBe(0);
});

it('recognizes the paid amount once across started, completed, and paid', function (): void {
    $owner = User::query()->firstOrFail();
    $property = revenueTestProperty();
    $session = revenueTestSession($owner, 24900);
    $order = revenueTestOrder($owner, 24900);

    $started = app(CheckoutSignalRecorder::class)->recordStarted($session);
    $completed = app(CheckoutSignalRecorder::class)->recordCompleted($session);
    $paid = app(OrderSignalRecorder::class)->recordPaid($order, 'txn-revenue-1', 'chip', 24900);

    expect($started->revenue_minor)->toBe(0)
        ->and($completed->revenue_minor)->toBe(0)
        ->and($completed->properties['total_minor'] ?? null)->toBe(24900)
        ->and($paid->revenue_minor)->toBe(24900)
        ->and($paid->properties['order_total_minor'] ?? null)->toBe(24900)
        ->and($paid->properties['paid_amount_minor'] ?? null)->toBe(24900);

    $metric = app(SignalMetricsAggregator::class)->aggregateForDate(Carbon::now(), $property);

    expect($metric->revenue_minor)->toBe(24900)
        ->and(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(3);
});

it('totals two partial payments at actual amounts while retries deduplicate', function (): void {
    $owner = User::query()->firstOrFail();
    $property = revenueTestProperty();
    $order = revenueTestOrder($owner, 24900);

    $recorder = app(OrderSignalRecorder::class);
    $first = $recorder->recordPaid($order, 'txn-partial-1', 'chip', 10000);
    $retry = $recorder->recordPaid($order, 'txn-partial-1', 'chip', 10000);
    $second = $recorder->recordPaid($order, 'txn-partial-2', 'chip', 14900);
    $otherGateway = $recorder->recordPaid($order, 'txn-partial-1', 'stripe', 10000);

    expect($retry->id)->toBe($first->id)
        ->and($second->id)->not->toBe($first->id)
        ->and($otherGateway->id)->not->toBe($first->id)
        ->and($first->revenue_minor)->toBe(10000)
        ->and($second->revenue_minor)->toBe(14900);

    $metric = app(SignalMetricsAggregator::class)->aggregateForDate(Carbon::now(), $property);

    expect($metric->revenue_minor)->toBe(34900)
        ->and(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(3);
});

it('records refunds with zero recognized revenue and a refund amount property', function (): void {
    $owner = User::query()->firstOrFail();
    $property = revenueTestProperty();
    $order = revenueTestOrder($owner, 24900);

    app(OrderSignalRecorder::class)->recordPaid($order, 'txn-revenue-2', 'chip', 24900);
    $refund = app(OrderSignalRecorder::class)->recordRefunded($order, 'refund-revenue-1', 24900, 'customer request');

    expect($refund)->not->toBeNull()
        ->and($refund->revenue_minor)->toBe(0)
        ->and($refund->properties['refund_amount_minor'] ?? null)->toBe(24900)
        ->and($refund->properties['refund_id'] ?? null)->toBe('refund-revenue-1')
        ->and($refund->properties['order_total_minor'] ?? null)->toBe(24900);

    $metric = app(SignalMetricsAggregator::class)->aggregateForDate(Carbon::now(), $property);

    expect($metric->revenue_minor)->toBe(24900);
});

it('keeps cart snapshot values out of recognized revenue', function (): void {
    $owner = User::query()->firstOrFail();
    revenueTestProperty();

    $event = new class($owner->getMorphClass(), (string) $owner->getKey())
    {
        public function __construct(
            public string $ownerType,
            public string $ownerId,
            public string $cartIdentifier = 'high-value-cart',
            public string $cartInstance = 'default',
            public string $sourceEventId = 'cart-evt-1',
            public int $totalMinor = 50000,
            public string $currency = 'MYR',
            public string $occurredAt = '2026-10-04T10:00:00+00:00',
            public int $subtotalMinor = 50000,
            public int $totalQuantity = 2,
            public int $uniqueItemCount = 1,
            public int $itemCount = 1,
        ) {}
    };

    $recorded = app(FilamentCartSignalRecorder::class)->record($event, 'cart.high_value.detected');

    expect($recorded)->not->toBeNull()
        ->and($recorded->revenue_minor)->toBe(0)
        ->and($recorded->properties['cart_total_minor'] ?? null)->toBe(50000);
});
