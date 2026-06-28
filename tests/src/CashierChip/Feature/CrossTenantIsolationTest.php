<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Payment\StoredPaymentMethod;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

uses(CashierChipTestCase::class);

function bindCashierChipOwner(?Model $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

it('scopes reads and blocks cross-tenant subscription item writes', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);
    config()->set('cashier-chip.features.owner.auto_assign_on_create', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'cashier-chip-owner-a-xt@example.com',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'cashier-chip-owner-b-xt@example.com',
    ]);

    bindCashierChipOwner($ownerB);

    /** @var Subscription $subscriptionB */
    $subscriptionB = Subscription::factory()->for($ownerB, 'billable')->create([
        'type' => 'default',
    ]);

    expect($subscriptionB->owner_type)->toBe($ownerB->getMorphClass());
    expect($subscriptionB->owner_id)->toBe($ownerB->getKey());

    bindCashierChipOwner($ownerA);

    expect(Subscription::forOwner($ownerA, false)->count())->toBe(0);
    expect(Subscription::forOwner($ownerB, false)->count())->toBe(1);

    expect(fn () => SubscriptionItem::query()->create([
        'subscription_id' => $subscriptionB->id,
        'chip_id' => 'si_' . Str::random(40),
        'chip_product' => 'prod_test',
        'chip_price' => 'price_test',
        'quantity' => 1,
        'unit_amount' => 1_000,
    ]))->toThrow(AuthorizationException::class);
});

it('fails closed for explicit owner scopes without a resolved owner', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);

    bindCashierChipOwner(null);

    expect(fn (): int => Subscription::query()->forOwner()->count())
        ->toThrow(NoCurrentOwnerException::class);

    expect(fn (): int => SubscriptionItem::query()->forOwner()->count())
        ->toThrow(NoCurrentOwnerException::class);

    expect(fn (): int => StoredPaymentMethod::query()->forOwner()->count())
        ->toThrow(NoCurrentOwnerException::class);

    OwnerContext::withOwner(null, function (): void {
        expect(Subscription::query()->forOwner()->count())->toBe(0);
        expect(SubscriptionItem::query()->forOwner()->count())->toBe(0);
        expect(StoredPaymentMethod::query()->forOwner()->count())->toBe(0);
    });
});

it('blocks subscription creation when billable differs from owner and owner-scoped validation is unavailable', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);
    config()->set('cashier-chip.features.owner.auto_assign_on_create', true);
    config()->set('cashier-chip.features.owner.validate_billable_owner', true);

    $owner = User::query()->create([
        'name' => 'Owner XT',
        'email' => 'cashier-chip-owner-xt-2@example.com',
    ]);

    $customer = User::query()->create([
        'name' => 'Customer XT',
        'email' => 'cashier-chip-customer-xt-2@example.com',
    ]);

    bindCashierChipOwner($owner);

    expect(fn () => Subscription::factory()->for($customer, 'billable')->create([
        'type' => 'default',
    ]))->toThrow(AuthorizationException::class);
});

it('allows subscription creation when validate_billable_owner is disabled', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);
    config()->set('cashier-chip.features.owner.auto_assign_on_create', true);
    config()->set('cashier-chip.features.owner.validate_billable_owner', false);

    $owner = User::query()->create([
        'name' => 'Owner Skip Validation',
        'email' => 'cashier-chip-owner-no-validate@example.com',
    ]);

    $customer = User::query()->create([
        'name' => 'Customer Skip Validation',
        'email' => 'cashier-chip-customer-no-validate@example.com',
    ]);

    bindCashierChipOwner($owner);

    $subscription = Subscription::factory()->for($customer, 'billable')->create([
        'type' => 'default',
    ]);

    expect($subscription->owner_type)->toBe($owner->getMorphClass());
    expect($subscription->owner_id)->toBe($owner->getKey());
    expect($subscription->billable_id)->toBe($customer->getKey());
});

it('blocks cross-tenant subscription creation when billable type uses a morph alias', function (): void {
    $originalMorphMap = Relation::morphMap();

    Relation::morphMap(['cashier-chip-user' => User::class], false);

    try {
        config()->set('cashier-chip.features.owner.enabled', true);
        config()->set('cashier-chip.features.owner.include_global', false);
        config()->set('cashier-chip.features.owner.auto_assign_on_create', true);
        config()->set('cashier-chip.features.owner.validate_billable_owner', true);

        $owner = User::query()->create([
            'name' => 'Owner Morph Alias',
            'email' => 'cashier-chip-owner-morph@example.com',
        ]);

        $customer = User::query()->create([
            'name' => 'Customer Morph Alias',
            'email' => 'cashier-chip-customer-morph@example.com',
        ]);

        bindCashierChipOwner($owner);

        expect(fn () => Subscription::factory()->for($customer, 'billable')->create([
            'type' => 'default',
        ]))->toThrow(AuthorizationException::class);
    } finally {
        Relation::morphMap($originalMorphMap, false);
    }
});

it('blocks cross-tenant payment method writes for billables in owner mode', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);
    config()->set('cashier-chip.features.owner.auto_assign_on_create', true);
    config()->set('cashier-chip.features.owner.validate_billable_owner', true);

    $owner = User::query()->create([
        'name' => 'Owner Payment Method',
        'email' => 'cashier-chip-owner-payment-method@example.com',
    ]);

    $customer = User::query()->create([
        'name' => 'Customer Payment Method',
        'email' => 'cashier-chip-customer-payment-method@example.com',
    ]);

    bindCashierChipOwner($owner);

    expect(fn () => Cashier::paymentMethodStore()->saveForBillable(
        $customer,
        'tok_cross_tenant',
        ['type' => 'card'],
    ))->toThrow(AuthorizationException::class);
});
