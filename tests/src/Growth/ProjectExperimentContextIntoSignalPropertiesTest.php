<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\ProjectExperimentContextIntoSignalProperties;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Orders\Models\Order;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Str;

function growthProjectionOwner(): User
{
    return User::query()->create([
        'name' => 'Growth Projection Owner ' . Str::random(6),
        'email' => 'growth-projection-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function growthProjectionTrackedProperty(User $owner): TrackedProperty
{
    return OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Growth Projection Property ' . Str::random(6),
        'slug' => 'growth-projection-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));
}

function growthProjectionExperiment(User $owner, TrackedProperty $trackedProperty, string $moduleType, string $suffix): array
{
    return OwnerContext::withOwner($owner, function () use ($moduleType, $suffix, $trackedProperty): array {
        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'name' => 'Projection Experiment ' . $suffix,
            'slug' => 'projection-' . Str::slug($suffix),
            'module_type' => $moduleType,
            'status' => 'active',
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => Str::upper($suffix),
            'name' => 'Variant ' . Str::upper($suffix),
            'position' => 1,
            'traffic_percentage' => 100,
            'is_control' => $suffix === 'a',
        ]);

        return [$experiment, $variant];
    });
}

function growthProjectionIdentity(User $owner, TrackedProperty $trackedProperty, string $customerId, string $cartId): SignalIdentity
{
    return OwnerContext::withOwner($owner, fn (): SignalIdentity => SignalIdentity::query()->create([
        'tracked_property_id' => $trackedProperty->getKey(),
        'external_id' => $customerId,
        'anonymous_id' => $cartId,
        'email' => 'projection-customer-' . Str::lower(Str::random(8)) . '@example.com',
    ]));
}

function growthProjectionAssignment(User $owner, Experiment $experiment, Variant $variant, SignalIdentity $identity, string $cartId): Assignment
{
    return OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variant->getKey(),
        'signal_identity_id' => $identity->getKey(),
        'subject_key' => 'anonymous:' . $cartId,
        'bucket' => 0,
        'assigned_at' => now(),
        'first_exposed_at' => now(),
        'last_seen_at' => now(),
    ]));
}

it('projects a single experiment context into checkout properties', function (): void {
    $owner = growthProjectionOwner();
    $trackedProperty = growthProjectionTrackedProperty($owner);
    [$experiment, $variant] = growthProjectionExperiment($owner, $trackedProperty, 'sales_page_test', 'a');
    $identity = growthProjectionIdentity($owner, $trackedProperty, 'customer-projection-1', 'cart-projection-1');
    $assignment = growthProjectionAssignment($owner, $experiment, $variant, $identity, 'cart-projection-1');

    $checkoutSession = OwnerContext::withOwner($owner, fn (): CheckoutSession => CheckoutSession::query()->create([
        'cart_id' => 'cart-projection-1',
        'customer_id' => 'customer-projection-1',
        'grand_total' => 29900,
        'currency' => 'MYR',
    ]));

    $properties = app(ProjectExperimentContextIntoSignalProperties::class)->handle($checkoutSession, $trackedProperty, [
        'checkout_session_id' => $checkoutSession->getKey(),
    ]);

    expect($properties['experiment_id'])->toBe((string) $experiment->getKey())
        ->and($properties['variant_id'])->toBe((string) $variant->getKey())
        ->and($properties['assignment_id'])->toBe((string) $assignment->getKey())
        ->and($properties['module_type'])->toBe('sales_page_test')
        ->and($properties['experiment_contexts'])->toHaveCount(1)
        ->and(data_get($properties, 'experiment_contexts.0.experiment_id'))->toBe((string) $experiment->getKey())
        ->and(data_get($properties, 'experiment_contexts.0.variant_id'))->toBe((string) $variant->getKey());
});

it('projects multiple experiment contexts into order properties when the same buyer participates in multiple tests', function (): void {
    $owner = growthProjectionOwner();
    $trackedProperty = growthProjectionTrackedProperty($owner);
    [$experimentA, $variantA] = growthProjectionExperiment($owner, $trackedProperty, 'sales_page_test', 'a');
    [$experimentB, $variantB] = growthProjectionExperiment($owner, $trackedProperty, 'pricing_test', 'b');
    $identity = growthProjectionIdentity($owner, $trackedProperty, 'customer-projection-2', 'cart-projection-2');

    growthProjectionAssignment($owner, $experimentA, $variantA, $identity, 'cart-projection-2');
    growthProjectionAssignment($owner, $experimentB, $variantB, $identity, 'cart-projection-2');

    $order = OwnerContext::withOwner($owner, fn (): Order => Order::query()->create([
        'customer_id' => 'customer-projection-2',
        'grand_total' => 49900,
        'currency' => 'MYR',
        'metadata' => [
            'checkout_session_id' => 'checkout-projection-2',
            'cart_id' => 'cart-projection-2',
        ],
        'paid_at' => now(),
    ]));

    $properties = app(ProjectExperimentContextIntoSignalProperties::class)->handle($order, $trackedProperty, [
        'order_id' => $order->getKey(),
    ]);

    expect($properties['experiment_contexts'])->toHaveCount(2)
        ->and(collect($properties['experiment_contexts'])->pluck('experiment_id')->all())
            ->toEqualCanonicalizing([(string) $experimentA->getKey(), (string) $experimentB->getKey()])
        ->and($properties)->toHaveKeys(['experiment_id', 'variant_id', 'assignment_id']);
});

it('does not enrich properties when the growth signals integration is disabled', function (): void {
    $owner = growthProjectionOwner();
    $trackedProperty = growthProjectionTrackedProperty($owner);
    [$experiment, $variant] = growthProjectionExperiment($owner, $trackedProperty, 'sales_page_test', 'c');
    $identity = growthProjectionIdentity($owner, $trackedProperty, 'customer-projection-3', 'cart-projection-3');

    growthProjectionAssignment($owner, $experiment, $variant, $identity, 'cart-projection-3');

    $checkoutSession = OwnerContext::withOwner($owner, fn (): CheckoutSession => CheckoutSession::query()->create([
        'cart_id' => 'cart-projection-3',
        'customer_id' => 'customer-projection-3',
        'grand_total' => 39900,
        'currency' => 'MYR',
    ]));

    config()->set('growth.integrations.signals.enabled', false);

    $properties = app(ProjectExperimentContextIntoSignalProperties::class)->handle($checkoutSession, $trackedProperty, [
        'checkout_session_id' => $checkoutSession->getKey(),
    ]);

    expect($properties)->toBe([
        'checkout_session_id' => $checkoutSession->getKey(),
    ]);

    config()->set('growth.integrations.signals.enabled', true);
});