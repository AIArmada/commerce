<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Events\PaymentRefunded;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventOrderDriver;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Models\OrderRefund;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('orders.owner.enabled', false);
});

it('matches, correlates and completes a trusted provider refund confirmation for the owning registration', function (): void {
    $order = Order::factory()->paid()->create([
        'subtotal' => 5000,
        'shipping_total' => 0,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 5000,
        'currency' => 'MYR',
    ]);

    $payment = OrderPayment::query()->create([
        'order_id' => $order->getKey(),
        'gateway' => 'stripe',
        'transaction_id' => 'pay_123',
        'amount' => 5000,
        'currency' => 'MYR',
        'status' => PaymentStatus::Completed,
        'metadata' => ['payment_id' => 'pay_123', 'provider' => 'stripe'],
        'paid_at' => now(),
    ]);

    $event = Event::factory()->create();
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'status' => 'refund_pending',
        'payment_status' => 'paid',
        'external_order_id' => $order->getKey(),
        'external_order_type' => Order::class,
        'metadata' => ['refund' => ['original_status' => 'confirmed']],
    ]);

    $refund = OrderRefund::query()->create([
        'order_id' => $order->getKey(),
        'payment_id' => $payment->getKey(),
        'gateway' => 'stripe',
        'transaction_id' => 'event-refund-' . $order->getKey(),
        'allocation_key' => 'event-refund-' . $order->getKey(),
        'amount' => 5000,
        'currency' => 'MYR',
        'status' => RefundStatus::Pending,
        'reason' => 'Registration refund',
        'metadata' => [
            'event_registration_id' => $registration->getKey(),
            'payment_id' => 'pay_123',
            'provider' => 'stripe',
        ],
    ]);

    $driver = app(EventOrderDriver::class);

    $match = $driver->matchProviderRefundConfirmation(
        provider: 'stripe',
        providerRefundId: 're_123',
        relatedPaymentId: 'pay_123',
        amount: 5000,
        currency: 'MYR',
        eventRegistrationId: (string) $registration->getKey(),
    );

    expect($match->matchedCount)->toBe(1)
        ->and($match->pending)->toBeTrue()
        ->and($match->refundId)->toBe($refund->getKey());

    expect($driver->correlateRefundProviderReference($match->refundId, 're_123', 'pay_123'))->toBeTrue();

    expect($driver->correlateRefundProviderReference($match->refundId, 're_conflict', 'pay_123'))->toBeFalse();

    $driver->completeRefund($match->refundId, 're_123');

    expect($driver->correlateRefundProviderReference($match->refundId, 're_123', 'pay_123'))->toBeTrue()
        ->and($refund->fresh()->metadata['provider_refund_id'])->toBe('re_123');

    expect($refund->fresh()->status)->toBe(RefundStatus::Completed);
});

it('does not complete an ambiguous provider refund match', function (): void {
    $order = Order::factory()->paid()->create([
        'subtotal' => 5000,
        'shipping_total' => 0,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 10000,
        'currency' => 'MYR',
    ]);

    $payment = OrderPayment::query()->create([
        'order_id' => $order->getKey(),
        'gateway' => 'stripe',
        'transaction_id' => 'pay_ambiguous',
        'amount' => 10000,
        'currency' => 'MYR',
        'status' => PaymentStatus::Completed,
        'metadata' => ['payment_id' => 'pay_ambiguous', 'provider' => 'stripe'],
        'paid_at' => now(),
    ]);

    $event = Event::factory()->create();
    $registration = EventRegistration::factory()->create([
        'event_id' => $event->getKey(),
        'status' => 'refund_pending',
        'payment_status' => 'paid',
        'external_order_id' => $order->getKey(),
        'external_order_type' => Order::class,
        'metadata' => ['refund' => ['original_status' => 'confirmed']],
    ]);

    foreach ([1, 2] as $index) {
        OrderRefund::query()->create([
            'order_id' => $order->getKey(),
            'payment_id' => $payment->getKey(),
            'gateway' => 'stripe',
            'transaction_id' => 'event-refund-ambiguous-' . $index,
            'allocation_key' => 'event-refund-ambiguous-' . $index,
            'amount' => 5000,
            'currency' => 'MYR',
            'status' => RefundStatus::Pending,
            'reason' => 'Registration refund',
            'metadata' => [
                'event_registration_id' => $registration->getKey(),
                'payment_id' => 'pay_ambiguous',
                'provider' => 'stripe',
            ],
        ]);
    }

    $match = app(EventOrderDriver::class)->matchProviderRefundConfirmation(
        provider: 'stripe',
        providerRefundId: 're_ambiguous',
        relatedPaymentId: 'pay_ambiguous',
        amount: 5000,
        currency: 'MYR',
        eventRegistrationId: (string) $registration->getKey(),
    );

    expect($match->matchedCount)->toBe(2)
        ->and($match->pending)->toBeFalse()
        ->and($match->refundId)->toBeNull()
        ->and(OrderRefund::query()->where('status', RefundStatus::Pending->value)->count())->toBe(2);
});

it('completes the refund and syncs the registration through the asynchronous payment refund listener', function (): void {
    config()->set('events.features.owner.enabled', true);
    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.auto_assign_on_create', true);

    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    [$registration, $refund] = OwnerContext::withOwner($owner, function (): array {
        $order = Order::factory()->paid()->create([
            'subtotal' => 5000,
            'shipping_total' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 5000,
            'currency' => 'MYR',
        ]);

        $payment = OrderPayment::query()->create([
            'order_id' => $order->getKey(),
            'gateway' => 'stripe',
            'transaction_id' => 'pay_async',
            'amount' => 5000,
            'currency' => 'MYR',
            'status' => PaymentStatus::Completed,
            'metadata' => ['payment_id' => 'pay_async', 'provider' => 'stripe'],
            'paid_at' => now(),
        ]);

        $event = Event::factory()->create();
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->getKey(),
            'status' => 'refund_pending',
            'payment_status' => 'paid',
            'external_order_id' => $order->getKey(),
            'external_order_type' => Order::class,
            'metadata' => ['refund' => ['original_status' => 'confirmed']],
        ]);

        $refund = OrderRefund::query()->create([
            'order_id' => $order->getKey(),
            'payment_id' => $payment->getKey(),
            'gateway' => 'stripe',
            'transaction_id' => 'event-refund-' . $order->getKey(),
            'allocation_key' => 'event-refund-' . $order->getKey(),
            'amount' => 5000,
            'currency' => 'MYR',
            'status' => RefundStatus::Pending,
            'reason' => 'Registration refund',
            'metadata' => [
                'event_registration_id' => $registration->getKey(),
                'event_registration_scope' => 'selected',
                'event_registration_ids' => [$registration->getKey()],
                'payment_id' => 'pay_async',
                'provider' => 'stripe',
            ],
        ]);

        return [$registration, $refund];
    });

    OwnerContext::withOwner($otherOwner, function () use ($refund): void {
        expect(fn () => app(EventOrderDriver::class)->completeRefund((string) $refund->getKey()))
            ->toThrow(ModelNotFoundException::class);
    });

    event(new PaymentRefunded(
        provider: 'stripe',
        paymentId: 're_async',
        relatedPaymentId: 'pay_async',
        amount: 5000,
        currency: 'MYR',
        metadata: ['event_registration_id' => $registration->getKey()],
    ));

    OwnerContext::withOwner($owner, function () use ($refund, $registration): void {
        expect($refund->fresh()->status)->toBe(RefundStatus::Completed)
            ->and($registration->fresh()->status->getValue())->toBe('refunded')
            ->and($registration->fresh()->payment_status)->toBe('refunded');
    });
});
