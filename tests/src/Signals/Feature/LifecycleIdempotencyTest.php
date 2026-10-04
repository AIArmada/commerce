<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Orders\Events\OrderRefunded;
use AIArmada\Orders\Models\Order;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\Recorders\AffiliateNetworkSignalRecorder;
use AIArmada\Signals\Services\Recorders\CheckoutSignalRecorder;
use AIArmada\Signals\Services\Recorders\OrderSignalRecorder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
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

function lifecycleTestProperty(): TrackedProperty
{
    return TrackedProperty::query()->create([
        'name' => 'Lifecycle Property',
        'slug' => 'lifecycle-property-' . Str::lower(Str::random(6)),
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);
}

function lifecycleTestSession(User $owner): CheckoutSession
{
    $session = CheckoutSession::query()->create([
        'cart_id' => 'lifecycle-cart-' . Str::lower(Str::random(6)),
        'customer_id' => $owner->id,
        'status' => 'pending',
    ]);
    $session->forceFill([
        'grand_total' => 10000,
        'currency' => 'MYR',
        'completed_at' => Carbon::now(),
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ])->save();

    return $session;
}

function lifecycleTestOrder(User $owner): Order
{
    $order = Order::query()->create([
        'order_number' => 'LC-' . Str::upper(Str::random(8)),
        'customer_id' => $owner->id,
        'customer_type' => $owner->getMorphClass(),
        'grand_total' => 10000,
        'currency' => 'MYR',
        'paid_at' => Carbon::now(),
    ]);
    $order->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ])->save();

    return $order;
}

it('deduplicates checkout started retries while keeping completed distinct', function (): void {
    $owner = User::query()->firstOrFail();
    lifecycleTestProperty();
    $session = lifecycleTestSession($owner);

    $recorder = app(CheckoutSignalRecorder::class);
    $first = $recorder->recordStarted($session);
    $second = $recorder->recordStarted($session);
    $completed = $recorder->recordCompleted($session);

    expect($first)->not->toBeNull()
        ->and($second->id)->toBe($first->id)
        ->and($completed->id)->not->toBe($first->id)
        ->and($completed->event_name)->toBe('checkout.completed')
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(2);
});

it('deduplicates checkout completed retries', function (): void {
    $owner = User::query()->firstOrFail();
    lifecycleTestProperty();
    $session = lifecycleTestSession($owner);

    $recorder = app(CheckoutSignalRecorder::class);
    $first = $recorder->recordCompleted($session);
    $second = $recorder->recordCompleted($session);

    expect($second->id)->toBe($first->id)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1);
});

it('deduplicates order paid retries per gateway transaction without collapsing distinct payments', function (): void {
    $owner = User::query()->firstOrFail();
    lifecycleTestProperty();
    $order = lifecycleTestOrder($owner);

    $recorder = app(OrderSignalRecorder::class);
    $first = $recorder->recordPaid($order, 'txn-lifecycle-1', 'chip', 4000);
    $retry = $recorder->recordPaid($order, 'txn-lifecycle-1', 'chip', 4000);

    expect($first)->not->toBeNull()
        ->and($retry->id)->toBe($first->id)
        ->and($first->revenue_minor)->toBe(4000);

    $order->forceFill(['updated_at' => Carbon::now()->addMinute()])->save();
    $retryAfterUpdate = $recorder->recordPaid($order->refresh(), 'txn-lifecycle-1', 'chip', 4000);

    expect($retryAfterUpdate->id)->toBe($first->id);

    $sameTxnOtherGateway = $recorder->recordPaid($order->refresh(), 'txn-lifecycle-1', 'stripe', 4000);
    $secondPayment = $recorder->recordPaid($order->refresh(), 'txn-lifecycle-2', 'chip', 6000);

    expect($sameTxnOtherGateway->id)->not->toBe($first->id)
        ->and($secondPayment->id)->not->toBe($first->id)
        ->and($secondPayment->id)->not->toBe($sameTxnOtherGateway->id)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(3);
});

it('deduplicates refunded event retries by stable refund id through the real listener', function (): void {
    $owner = User::query()->firstOrFail();
    lifecycleTestProperty();
    $order = lifecycleTestOrder($owner);
    $frozen = Carbon::now();
    Carbon::setTestNow($frozen);
    $order->forceFill(['updated_at' => $frozen])->save();
    $order->refresh();

    Event::dispatch(new OrderRefunded($order, 4000, 'partial return', 'refund-lifecycle-1'));
    Event::dispatch(new OrderRefunded($order, 4000, 'partial return', 'refund-lifecycle-1'));

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1);

    $order->forceFill(['updated_at' => $frozen->copy()->addMinute()])->save();
    Event::dispatch(new OrderRefunded($order->refresh(), 4000, 'partial return', 'refund-lifecycle-1'));

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1);

    $order->forceFill(['updated_at' => $frozen])->save();
    Event::dispatch(new OrderRefunded($order->refresh(), 4000, 'partial return', 'refund-lifecycle-2'));

    $events = SignalEvent::query()->withoutOwnerScope()->orderBy('created_at')->get();

    expect($events)->toHaveCount(2)
        ->and($events[0]->idempotency_key)->toStartWith('order-refunded:')
        ->and($events[1]->idempotency_key)->toStartWith('order-refunded:')
        ->and(mb_strlen((string) $events[0]->idempotency_key))->toBeLessThanOrEqual(255)
        ->and(mb_strlen((string) $events[1]->idempotency_key))->toBeLessThanOrEqual(255)
        ->and($events[0]->idempotency_key)->not->toContain('refund-lifecycle-1')
        ->and($events[0]->source_event_id)->toBe('refund-lifecycle-1')
        ->and($events[1]->source_event_id)->toBe('refund-lifecycle-2')
        ->and($events[1]->properties['refund_id'] ?? null)->toBe('refund-lifecycle-2');

    Carbon::setTestNow();
});

it('bounds paid idempotency keys for max-length gateway and transaction ids', function (): void {
    $owner = User::query()->firstOrFail();
    lifecycleTestProperty();
    $order = lifecycleTestOrder($owner);

    $gateway = str_repeat('g', 50);
    $transactionId = str_repeat('t', 255);

    $recorder = app(OrderSignalRecorder::class);
    $first = $recorder->recordPaid($order, $transactionId, $gateway, 4000);
    $retry = $recorder->recordPaid($order->refresh(), $transactionId, $gateway, 4000);

    expect($first)->not->toBeNull()
        ->and(mb_strlen((string) $first->idempotency_key))->toBeLessThanOrEqual(255)
        ->and($first->idempotency_key)->toStartWith('order-paid:')
        ->and($first->source_event_id)->toBe($transactionId)
        ->and($first->properties['transaction_id'] ?? null)->toBe($transactionId)
        ->and($first->properties['gateway'] ?? null)->toBe($gateway)
        ->and($retry->id)->toBe($first->id)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1);
});

it('keeps delimiter-containing paid tuples distinct without collapsing retries', function (): void {
    $owner = User::query()->firstOrFail();
    lifecycleTestProperty();
    $order = lifecycleTestOrder($owner);

    $recorder = app(OrderSignalRecorder::class);
    $first = $recorder->recordPaid($order, 'c', 'a:b', 1000);
    $second = $recorder->recordPaid($order->refresh(), 'b:c', 'a', 1000);
    $retry = $recorder->recordPaid($order->refresh(), 'c', 'a:b', 1000);

    expect($first->id)->not->toBe($second->id)
        ->and($first->idempotency_key)->not->toBe($second->idempotency_key)
        ->and($retry->id)->toBe($first->id)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(2);
});

it('records repeated affiliate network interactions without collapsing them', function (): void {
    $property = lifecycleTestProperty();

    $recorder = app(AffiliateNetworkSignalRecorder::class);
    $first = $recorder->recordOfferCreated($property);
    $retry = $recorder->recordOfferCreated($property);

    expect($first)->not->toBeNull()
        ->and($retry->id)->not->toBe($first->id)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(2);
});
