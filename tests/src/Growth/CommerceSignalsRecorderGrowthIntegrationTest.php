<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Orders\Models\Order;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\CommerceSignalsRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

function growthRecorderOwner(): User
{
    return User::query()->create([
        'name' => 'Growth Recorder Owner ' . Str::random(6),
        'email' => 'growth-recorder-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function growthRecorderTrackedProperty(User $owner): TrackedProperty
{
    return OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Growth Recorder Property ' . Str::random(6),
        'slug' => 'growth-recorder-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));
}

function growthRecorderExperimentContext(User $owner, TrackedProperty $trackedProperty, string $customerId, string $cartId): array
{
    return OwnerContext::withOwner($owner, function () use ($cartId, $customerId, $trackedProperty): array {
        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'module_type' => 'pricing_test',
            'status' => 'active',
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'B',
            'name' => 'Premium Price',
            'traffic_percentage' => 100,
            'position' => 1,
        ]);

        $identity = SignalIdentity::query()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'external_id' => $customerId,
            'anonymous_id' => $cartId,
            'email' => 'growth-recorder-customer-' . Str::lower(Str::random(8)) . '@example.com',
        ]);

        $assignment = Assignment::query()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variant->getKey(),
            'signal_identity_id' => $identity->getKey(),
            'subject_key' => 'anonymous:' . $cartId,
            'bucket' => 0,
            'assigned_at' => now(),
            'first_exposed_at' => now(),
            'last_seen_at' => now(),
        ]);

        return [$experiment, $variant, $identity, $assignment];
    });
}

it('records checkout and order signals with projected experiment context', function (): void {
    $owner = growthRecorderOwner();
    $trackedProperty = growthRecorderTrackedProperty($owner);
    [$experiment, $variant] = growthRecorderExperimentContext($owner, $trackedProperty, 'customer-recorder-1', 'cart-recorder-1');

    $checkoutSession = OwnerContext::withOwner($owner, fn (): CheckoutSession => CheckoutSession::query()->create([
        'cart_id' => 'cart-recorder-1',
        'customer_id' => 'customer-recorder-1',
        'grand_total' => 129900,
        'currency' => 'MYR',
        'selected_payment_gateway' => 'chip',
        'selected_shipping_method' => 'express',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
    ]));

    $order = OwnerContext::withOwner($owner, fn (): Order => Order::query()->create([
        'customer_id' => 'customer-recorder-1',
        'grand_total' => 129900,
        'currency' => 'MYR',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'metadata' => [
            'checkout_session_id' => $checkoutSession->getKey(),
            'cart_id' => 'cart-recorder-1',
        ],
        'paid_at' => now(),
    ]));

    $recorder = app(CommerceSignalsRecorder::class);

    $checkoutStarted = $recorder->recordCheckoutStarted($checkoutSession);
    $orderPaid = $recorder->recordOrderPaid($order, 'txn-growth-1', 'chip');
    $orderRefunded = $recorder->recordOrderRefunded($order, 1500, 'customer-request');

    expect($checkoutStarted)->not->toBeNull()
        ->and($orderPaid)->not->toBeNull()
        ->and($orderRefunded)->not->toBeNull()
        ->and(data_get($checkoutStarted?->properties, 'experiment_contexts.0.experiment_id'))->toBe((string) $experiment->getKey())
        ->and(data_get($orderPaid?->properties, 'experiment_contexts.0.variant_id'))->toBe((string) $variant->getKey())
        ->and(data_get($orderPaid?->properties, 'cart_id'))->toBe('cart-recorder-1')
        ->and(data_get($orderPaid?->properties, 'checkout_session_id'))->toBe((string) $checkoutSession->getKey())
        ->and(data_get($orderRefunded?->properties, 'refund_reason'))->toBe('customer-request')
        ->and($orderRefunded?->event_name)->toBe('order.refunded');
});

it('uses the tracked property owner for growth enrichment when only a resolver owner is present', function (): void {
    $owner = growthRecorderOwner();
    $foreignOwner = growthRecorderOwner();
    $trackedProperty = growthRecorderTrackedProperty($owner);
    [$experiment, $variant] = growthRecorderExperimentContext($owner, $trackedProperty, 'customer-recorder-2', 'cart-recorder-2');

    $order = OwnerContext::withOwner($owner, fn (): Order => Order::query()->create([
        'customer_id' => 'customer-recorder-2',
        'grand_total' => 219900,
        'currency' => 'MYR',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'metadata' => [
            'cart_id' => 'cart-recorder-2',
        ],
        'paid_at' => now(),
    ]));

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($foreignOwner) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly User $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $recorder = app(CommerceSignalsRecorder::class);
    $orderPaid = $recorder->recordOrderPaid($order, 'txn-growth-2', 'chip');

    expect($orderPaid)->not->toBeNull()
        ->and(data_get($orderPaid?->properties, 'experiment_contexts.0.experiment_id'))->toBe((string) $experiment->getKey())
        ->and(data_get($orderPaid?->properties, 'experiment_contexts.0.variant_id'))->toBe((string) $variant->getKey())
        ->and(data_get($orderPaid?->properties, 'order_id'))->toBe((string) $order->getKey());
});
