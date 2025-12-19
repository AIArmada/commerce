<?php

declare(strict_types=1);

use AIArmada\CashierChip\Subscription;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Commerce\Tests\FilamentCashierChip\Fixtures\User;
use AIArmada\Commerce\Tests\FilamentCashierChip\TestCase;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

uses(TestCase::class);

function bindFilamentCashierChipOwner(?Model $owner): void
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

it('scopes SubscriptionResource queries to the current owner', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);
    config()->set('cashier-chip.features.owner.auto_assign_on_create', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'filament-cashier-chip-owner-a-xt@example.com',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'filament-cashier-chip-owner-b-xt@example.com',
    ]);

    bindFilamentCashierChipOwner($ownerB);

    $customerB = User::query()->create([
        'name' => 'Customer B',
        'email' => 'filament-cashier-chip-customer-b-xt@example.com',
    ]);

    $subscriptionB = Subscription::query()->create([
        'user_id' => $customerB->id,
        'type' => 'default',
        'chip_id' => 'sub_' . Str::random(40),
        'chip_status' => Subscription::STATUS_ACTIVE,
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
        'recurring_token' => 'tok_' . Str::random(32),
        'next_billing_at' => now()->addMonth(),
    ]);

    bindFilamentCashierChipOwner($ownerA);

    expect(SubscriptionResource::getEloquentQuery()->whereKey($subscriptionB->id)->exists())->toBeFalse();
    expect(SubscriptionResource::getNavigationBadge())->toBeNull();
});
