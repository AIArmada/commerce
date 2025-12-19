<?php

declare(strict_types=1);

use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
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

    $customerB = User::query()->create([
        'name' => 'Customer B',
        'email' => 'cashier-chip-customer-b-xt@example.com',
    ]);

    /** @var Subscription $subscriptionB */
    $subscriptionB = Subscription::factory()->create([
        'user_id' => $customerB->id,
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
