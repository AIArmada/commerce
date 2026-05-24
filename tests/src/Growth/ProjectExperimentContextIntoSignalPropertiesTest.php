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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

    $properties = OwnerContext::withOwner($owner, fn (): array => app(ProjectExperimentContextIntoSignalProperties::class)->handle($checkoutSession, $trackedProperty, [
        'checkout_session_id' => $checkoutSession->getKey(),
    ]));

    expect($properties['experiment_id'])->toBe((string) $experiment->getKey())
        ->and($properties['variant_id'])->toBe((string) $variant->getKey())
        ->and($properties['assignment_id'])->toBe((string) $assignment->getKey())
        ->and($properties['module_type'])->toBe('sales_page_test')
        ->and($properties['experiment_contexts'])->toHaveCount(1)
        ->and(data_get($properties, 'experiment_contexts.0.experiment_id'))->toBe((string) $experiment->getKey())
        ->and(data_get($properties, 'experiment_contexts.0.variant_id'))->toBe((string) $variant->getKey());
});

it('does not throw when strict missing-attribute protection is enabled for checkout sessions without metadata', function (): void {
    $previous = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes(true);

    try {
        $owner = growthProjectionOwner();
        $trackedProperty = growthProjectionTrackedProperty($owner);
        [$experiment, $variant] = growthProjectionExperiment($owner, $trackedProperty, 'sales_page_test', 'strict');
        $identity = growthProjectionIdentity($owner, $trackedProperty, 'customer-projection-strict', 'cart-projection-strict');
        $assignment = growthProjectionAssignment($owner, $experiment, $variant, $identity, 'cart-projection-strict');

        $checkoutSession = OwnerContext::withOwner($owner, fn (): CheckoutSession => CheckoutSession::query()->create([
            'cart_id' => 'cart-projection-strict',
            'customer_id' => 'customer-projection-strict',
            'grand_total' => 19900,
            'currency' => 'MYR',
        ]));

        $properties = OwnerContext::withOwner($owner, fn (): array => app(ProjectExperimentContextIntoSignalProperties::class)->handle($checkoutSession, $trackedProperty, [
            'checkout_session_id' => $checkoutSession->getKey(),
        ]));

        expect($properties['experiment_id'])->toBe((string) $experiment->getKey())
            ->and($properties['variant_id'])->toBe((string) $variant->getKey())
            ->and($properties['assignment_id'])->toBe((string) $assignment->getKey())
            ->and($properties['experiment_contexts'])->toHaveCount(1);
    } finally {
        Model::preventAccessingMissingAttributes($previous);
    }
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

    $properties = OwnerContext::withOwner($owner, fn (): array => app(ProjectExperimentContextIntoSignalProperties::class)->handle($order, $trackedProperty, [
        'order_id' => $order->getKey(),
    ]));

    expect($properties['experiment_contexts'])->toHaveCount(2)
        ->and(collect($properties['experiment_contexts'])->pluck('experiment_id')->all())
        ->toEqualCanonicalizing([(string) $experimentA->getKey(), (string) $experimentB->getKey()])
        ->and($properties)->toHaveKeys(['experiment_id', 'variant_id', 'assignment_id']);
});

it('skips newer invalid assignments when projecting experiment contexts for a source', function (): void {
    $owner = growthProjectionOwner();
    $trackedProperty = growthProjectionTrackedProperty($owner);
    [$experiment, $variant] = growthProjectionExperiment($owner, $trackedProperty, 'sales_page_test', 'fallback');
    $identity = growthProjectionIdentity($owner, $trackedProperty, 'customer-projection-fallback', 'cart-projection-fallback');
    $assignment = growthProjectionAssignment($owner, $experiment, $variant, $identity, 'cart-projection-fallback');

    OwnerContext::withOwner($owner, function () use ($assignment): void {
        $assignment->subject_key = 'identity-only:cart-projection-fallback';
        $assignment->save();
    });

    $invalidTimestamp = now()->addMinute();

    DB::table((new Assignment)->getTable())->insert([
        'id' => (string) Str::uuid(),
        'experiment_id' => $experiment->getKey(),
        'variant_id' => (string) Str::uuid(),
        'signal_identity_id' => null,
        'signal_session_id' => null,
        'subject_key' => 'anonymous:cart-projection-fallback',
        'bucket' => 1,
        'metadata' => json_encode([]),
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'assigned_at' => $invalidTimestamp,
        'first_exposed_at' => $invalidTimestamp,
        'last_seen_at' => $invalidTimestamp,
        'created_at' => $invalidTimestamp,
        'updated_at' => $invalidTimestamp,
    ]);

    $checkoutSession = OwnerContext::withOwner($owner, fn (): CheckoutSession => CheckoutSession::query()->create([
        'cart_id' => 'cart-projection-fallback',
        'customer_id' => 'customer-projection-fallback',
        'grand_total' => 29900,
        'currency' => 'MYR',
    ]));

    $properties = OwnerContext::withOwner($owner, fn (): array => app(ProjectExperimentContextIntoSignalProperties::class)->handle($checkoutSession, $trackedProperty, [
        'checkout_session_id' => $checkoutSession->getKey(),
    ]));

    expect($properties['experiment_id'])->toBe((string) $experiment->getKey())
        ->and($properties['variant_id'])->toBe((string) $variant->getKey())
        ->and($properties['assignment_id'])->toBe((string) $assignment->getKey())
        ->and($properties['experiment_contexts'])->toHaveCount(1)
        ->and(data_get($properties, 'experiment_contexts.0.assignment_id'))->toBe((string) $assignment->getKey());
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

it('preserves existing null-valued properties while enriching experiment context', function (): void {
    $owner = growthProjectionOwner();
    $trackedProperty = growthProjectionTrackedProperty($owner);
    [$experiment, $variant] = growthProjectionExperiment($owner, $trackedProperty, 'sales_page_test', 'null-preservation');
    $identity = growthProjectionIdentity($owner, $trackedProperty, 'customer-projection-null', 'cart-projection-null');

    growthProjectionAssignment($owner, $experiment, $variant, $identity, 'cart-projection-null');

    $checkoutSession = OwnerContext::withOwner($owner, fn (): CheckoutSession => CheckoutSession::query()->create([
        'cart_id' => 'cart-projection-null',
        'customer_id' => 'customer-projection-null',
        'grand_total' => 39900,
        'currency' => 'MYR',
    ]));

    $properties = OwnerContext::withOwner($owner, fn (): array => app(ProjectExperimentContextIntoSignalProperties::class)->handle($checkoutSession, $trackedProperty, [
        'checkout_session_id' => $checkoutSession->getKey(),
        'coupon_code' => null,
    ]));

    expect($properties)->toHaveKey('coupon_code')
        ->and($properties['coupon_code'])->toBeNull()
        ->and($properties['experiment_id'])->toBe((string) $experiment->getKey())
        ->and($properties['variant_id'])->toBe((string) $variant->getKey())
        ->and($properties['experiment_contexts'])->toHaveCount(1);
});

it('rejects enrichment when the tracked property is outside the current owner scope', function (): void {
    $ownerA = growthProjectionOwner();
    $ownerB = growthProjectionOwner();
    $trackedProperty = growthProjectionTrackedProperty($ownerA);
    [$experiment, $variant] = growthProjectionExperiment($ownerA, $trackedProperty, 'sales_page_test', 'd');
    $identity = growthProjectionIdentity($ownerA, $trackedProperty, 'customer-projection-4', 'cart-projection-4');

    growthProjectionAssignment($ownerA, $experiment, $variant, $identity, 'cart-projection-4');

    $checkoutSession = OwnerContext::withOwner($ownerB, fn (): CheckoutSession => CheckoutSession::query()->create([
        'cart_id' => 'cart-projection-4',
        'customer_id' => 'customer-projection-4',
        'grand_total' => 29900,
        'currency' => 'MYR',
    ]));

    expect(fn (): array => OwnerContext::withOwner($ownerB, fn (): array => app(ProjectExperimentContextIntoSignalProperties::class)->handle($checkoutSession, $trackedProperty, [
        'checkout_session_id' => $checkoutSession->getKey(),
    ])))
        ->toThrow(AuthorizationException::class, 'Tracked property is not accessible in the current owner scope.');
});

it('rejects enrichment when growth owner scoping is disabled but the tracked property is outside the current signals owner scope', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $ownerA = growthProjectionOwner();
    $ownerB = growthProjectionOwner();
    $trackedProperty = growthProjectionTrackedProperty($ownerA);
    [$experiment, $variant] = growthProjectionExperiment($ownerA, $trackedProperty, 'sales_page_test', 'tracked-property-only-scope');
    $identity = growthProjectionIdentity($ownerA, $trackedProperty, 'customer-projection-4b', 'cart-projection-4b');

    growthProjectionAssignment($ownerA, $experiment, $variant, $identity, 'cart-projection-4b');

    $checkoutSession = OwnerContext::withOwner($ownerB, fn (): CheckoutSession => CheckoutSession::query()->create([
        'cart_id' => 'cart-projection-4b',
        'customer_id' => 'customer-projection-4b',
        'grand_total' => 29900,
        'currency' => 'MYR',
    ]));

    expect(fn (): array => OwnerContext::withOwner($ownerB, fn (): array => app(ProjectExperimentContextIntoSignalProperties::class)->handle($checkoutSession, $trackedProperty, [
        'checkout_session_id' => $checkoutSession->getKey(),
    ])))
        ->toThrow(AuthorizationException::class, 'Tracked property is not accessible in the current owner scope.');
});

it('rejects enrichment from global tracked properties when include_global is disabled', function (): void {
    $owner = growthProjectionOwner();

    $trackedProperty = OwnerContext::withOwner(null, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Global Growth Projection Property ' . Str::random(6),
        'slug' => 'global-growth-projection-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
        'owner_type' => null,
        'owner_id' => null,
    ]));

    [$experiment, $variant] = OwnerContext::withOwner(null, function () use ($trackedProperty): array {
        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->global()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'name' => 'Global Projection Experiment',
            'slug' => 'global-projection-experiment-' . Str::lower(Str::random(6)),
            'module_type' => 'sales_page_test',
            'status' => 'active',
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->global()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'G',
            'name' => 'Global Variant',
            'position' => 1,
            'traffic_percentage' => 100,
            'is_control' => true,
        ]);

        return [$experiment, $variant];
    });

    $identity = OwnerContext::withOwner(null, fn (): SignalIdentity => SignalIdentity::query()->create([
        'tracked_property_id' => $trackedProperty->getKey(),
        'external_id' => 'customer-projection-global',
        'anonymous_id' => 'cart-projection-global',
        'email' => 'projection-global-' . Str::lower(Str::random(8)) . '@example.com',
        'owner_type' => null,
        'owner_id' => null,
    ]));

    OwnerContext::withOwner(null, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variant->getKey(),
        'signal_identity_id' => $identity->getKey(),
        'subject_key' => 'anonymous:cart-projection-global',
        'bucket' => 0,
        'assigned_at' => now(),
        'first_exposed_at' => now(),
        'last_seen_at' => now(),
        'owner_type' => null,
        'owner_id' => null,
    ]));

    $checkoutSession = OwnerContext::withOwner($owner, fn (): CheckoutSession => CheckoutSession::query()->create([
        'cart_id' => 'cart-projection-global',
        'customer_id' => 'customer-projection-global',
        'grand_total' => 29900,
        'currency' => 'MYR',
    ]));

    expect(fn (): array => OwnerContext::withOwner($owner, fn (): array => app(ProjectExperimentContextIntoSignalProperties::class)->handle($checkoutSession, $trackedProperty, [
        'checkout_session_id' => $checkoutSession->getKey(),
    ])))
        ->toThrow(AuthorizationException::class, 'Tracked property is not accessible in the current owner scope.');
});

it('enriches signals from global tracked properties when include_global is enabled', function (): void {
    config()->set('growth.features.owner.enabled', false);
    config()->set('signals.owner.include_global', true);

    $owner = growthProjectionOwner();

    $trackedProperty = OwnerContext::withOwner(null, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Global Include Projection Property ' . Str::random(6),
        'slug' => 'global-include-projection-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
        'owner_type' => null,
        'owner_id' => null,
    ]));

    [$experiment, $variant] = OwnerContext::withOwner(null, function () use ($trackedProperty): array {
        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->global()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'name' => 'Global Include Projection Experiment',
            'slug' => 'global-include-projection-experiment-' . Str::lower(Str::random(6)),
            'module_type' => 'sales_page_test',
            'status' => 'active',
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->global()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'GI',
            'name' => 'Global Include Variant',
            'position' => 1,
            'traffic_percentage' => 100,
            'is_control' => true,
        ]);

        return [$experiment, $variant];
    });

    $identity = OwnerContext::withOwner(null, fn (): SignalIdentity => SignalIdentity::query()->create([
        'tracked_property_id' => $trackedProperty->getKey(),
        'external_id' => 'customer-projection-global-include',
        'anonymous_id' => 'cart-projection-global-include',
        'email' => 'projection-global-include-' . Str::lower(Str::random(8)) . '@example.com',
        'owner_type' => null,
        'owner_id' => null,
    ]));

    OwnerContext::withOwner(null, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variant->getKey(),
        'signal_identity_id' => $identity->getKey(),
        'subject_key' => 'anonymous:cart-projection-global-include',
        'bucket' => 0,
        'assigned_at' => now(),
        'first_exposed_at' => now(),
        'last_seen_at' => now(),
        'owner_type' => null,
        'owner_id' => null,
    ]));

    $checkoutSession = OwnerContext::withOwner($owner, fn (): CheckoutSession => CheckoutSession::query()->create([
        'cart_id' => 'cart-projection-global-include',
        'customer_id' => 'customer-projection-global-include',
        'grand_total' => 29900,
        'currency' => 'MYR',
    ]));

    $enrichedProperties = OwnerContext::withOwner($owner, fn (): array => app(ProjectExperimentContextIntoSignalProperties::class)->handle($checkoutSession, $trackedProperty, [
        'checkout_session_id' => $checkoutSession->getKey(),
    ]));

    expect($enrichedProperties['experiment_id'])->toBe((string) $experiment->getKey())
        ->and($enrichedProperties['variant_id'])->toBe((string) $variant->getKey())
        ->and($enrichedProperties['assignment_id'])->toBeString()
        ->and($enrichedProperties['experiment_contexts'])->toHaveCount(1);
});

it('replays explicit experiment contexts that were already persisted on the source metadata', function (): void {
    $owner = growthProjectionOwner();
    $trackedProperty = growthProjectionTrackedProperty($owner);

    $order = OwnerContext::withOwner($owner, fn (): Order => Order::query()->create([
        'customer_id' => 'customer-replay-explicit',
        'grand_total' => 29900,
        'currency' => 'MYR',
        'metadata' => [
            'experiment_contexts' => [[
                'experiment_id' => 'exp-replay-explicit',
                'variant_id' => 'var-replay-explicit',
                'experiment_slug' => 'homepage-hero',
                'variant_code' => 'B',
            ]],
        ],
    ]));

    $properties = OwnerContext::withOwner($owner, fn (): array => app(ProjectExperimentContextIntoSignalProperties::class)->handle($order, $trackedProperty, [
        'order_id' => $order->getKey(),
    ]));

    expect($properties['experiment_id'])->toBe('exp-replay-explicit')
        ->and($properties['variant_id'])->toBe('var-replay-explicit')
        ->and($properties['variant_code'])->toBe('B')
        ->and($properties['experiment_contexts'])->toHaveCount(1)
        ->and(data_get($properties, 'experiment_contexts.0.experiment_slug'))->toBe('homepage-hero');
});

it('merges explicit experiment contexts from payment and billing payloads without dropping existing null properties', function (): void {
    $owner = growthProjectionOwner();
    $trackedProperty = growthProjectionTrackedProperty($owner);

    $checkoutSession = OwnerContext::withOwner($owner, fn (): CheckoutSession => CheckoutSession::query()->create([
        'cart_id' => 'cart-replay-nested',
        'customer_id' => 'customer-replay-nested',
        'grand_total' => 39900,
        'currency' => 'MYR',
        'billing_data' => [
            'metadata' => [
                'experiment_contexts' => [[
                    'experiment_id' => 'exp-billing-context',
                    'variant_id' => 'var-billing-context',
                    'variant_code' => 'BILLING',
                ]],
            ],
        ],
        'payment_data' => [
            'experiment_contexts' => [[
                'experiment_id' => 'exp-payment-context',
                'variant_id' => 'var-payment-context',
                'variant_code' => 'PAYMENT',
            ]],
        ],
    ]));

    $properties = OwnerContext::withOwner($owner, fn (): array => app(ProjectExperimentContextIntoSignalProperties::class)->handle($checkoutSession, $trackedProperty, [
        'checkout_session_id' => $checkoutSession->getKey(),
        'coupon_code' => null,
    ]));

    expect($properties)->toHaveKey('coupon_code')
        ->and($properties['coupon_code'])->toBeNull()
        ->and($properties['experiment_contexts'])->toHaveCount(2)
        ->and(collect($properties['experiment_contexts'])->pluck('experiment_id')->all())
        ->toEqualCanonicalizing(['exp-billing-context', 'exp-payment-context']);
});
