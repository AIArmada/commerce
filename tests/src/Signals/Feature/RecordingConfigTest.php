<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Orders\Models\Order;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\Recorders\CheckoutSignalRecorder;
use AIArmada\Signals\Services\Recorders\OrderSignalRecorder;
use AIArmada\Signals\Services\Recorders\SignalRecorderSupport;
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
        $table->json('metadata')->nullable();
        $table->timestamp('paid_at')->nullable()->index();
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
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });
});

function recordingTestProperty(): TrackedProperty
{
    return TrackedProperty::query()->create([
        'name' => 'Recording Property',
        'slug' => 'recording-property-' . Str::lower(Str::random(6)),
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);
}

function recordingTestSession(User $owner): CheckoutSession
{
    $session = CheckoutSession::query()->create([
        'cart_id' => 'recording-cart-' . Str::lower(Str::random(6)),
        'customer_id' => $owner->id,
        'status' => 'pending',
    ]);
    $session->forceFill([
        'grand_total' => 10000,
        'currency' => 'MYR',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ])->save();

    return $session;
}

function recordingTestOrder(User $owner): Order
{
    $order = Order::query()->create([
        'order_number' => 'RC-' . Str::upper(Str::random(8)),
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

it('honors literal dotted event names in the recording config', function (): void {
    config()->set('signals.recording.events', ['order.paid' => false]);

    expect(app(SignalRecorderSupport::class)->isEventRecordingEnabled('order.paid'))->toBeFalse()
        ->and(app(SignalRecorderSupport::class)->isEventRecordingEnabled('order.refunded'))->toBeTrue();
});

it('suppresses recorder output when the literal event is disabled', function (): void {
    $owner = User::query()->firstOrFail();
    recordingTestProperty();
    $session = recordingTestSession($owner);

    config()->set('signals.recording.events', ['checkout.started' => false]);

    expect(app(CheckoutSignalRecorder::class)->recordStarted($session))->toBeNull();

    config()->set('signals.recording.events', ['checkout.started' => true]);

    expect(app(CheckoutSignalRecorder::class)->recordStarted($session))->not->toBeNull();
});

it('selects the refund recording key from the transition, not the emitted name', function (): void {
    $owner = User::query()->firstOrFail();
    recordingTestProperty();
    $order = recordingTestOrder($owner);

    config()->set('signals.integrations.orders.refund_event_name', 'custom.refund.emitted');
    config()->set('signals.recording.events', ['order.refunded' => false]);

    expect(app(OrderSignalRecorder::class)->recordRefunded($order, 'refund-config-1', 1000, 'reason'))->toBeNull();

    config()->set('signals.recording.events', ['custom.refund.emitted' => false]);

    $event = app(OrderSignalRecorder::class)->recordRefunded($order, 'refund-config-1', 1000, 'reason');

    expect($event)->not->toBeNull()->and($event->event_name)->toBe('custom.refund.emitted');
});

it('suppresses disabled order recordings before mandatory source reads without a property', function (): void {
    config()->set('signals.recording.events', ['order.paid' => false, 'order.refunded' => false]);

    $order = new Order;
    $order->setRawAttributes([
        'id' => (string) Str::uuid(),
        'order_number' => 'SUPPRESSED-1',
        'currency' => 'MYR',
    ]);

    expect(app(OrderSignalRecorder::class)->recordPaid($order, 'txn-suppressed-1', 'chip', 1000))->toBeNull()
        ->and(app(OrderSignalRecorder::class)->recordRefunded($order, 'refund-suppressed-1', 1000))->toBeNull();
});
