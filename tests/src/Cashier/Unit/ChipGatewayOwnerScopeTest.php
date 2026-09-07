<?php

declare(strict_types=1);

use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\CashierChip\Billing\Cashier as CashierChip;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Subscription\Subscription as ChipSubscription;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\Tenant;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(CashierTestCase::class);

it('does not retrieve a CHIP subscription owned by another tenant', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);
    config()->set('cashier-chip.features.owner.auto_assign_on_create', true);
    config()->set('cashier-chip.features.owner.validate_billable_owner', false);

    if (! Schema::hasTable('cashier_chip_subscriptions')) {
        Schema::create('cashier_chip_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('billable_type');
            $table->string('billable_id');
            $table->string('owner_type')->nullable();
            $table->string('owner_id')->nullable();
            $table->string('type');
            $table->string('chip_id')->unique();
            $table->string('chip_status');
            $table->string('chip_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('billing_interval')->default('month');
            $table->integer('billing_interval_count')->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('cashier_chip_subscription_items')) {
        Schema::create('cashier_chip_subscription_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('subscription_id');
            $table->string('owner_type')->nullable();
            $table->string('owner_id')->nullable();
            $table->timestamps();
        });
    }

    CashierChip::useSubscriptionModel(ChipSubscription::class);

    $ownerA = Tenant::query()->create(['name' => 'Tenant A']);
    $ownerB = Tenant::query()->create(['name' => 'Tenant B']);

    $subscription = OwnerContext::withOwner($ownerA, function () use ($ownerA): ChipSubscription {
        return ChipSubscription::query()->create([
            'billable_type' => Tenant::class,
            'billable_id' => (string) $ownerA->getKey(),
            'type' => 'default',
            'chip_id' => 'chip-subscription-a',
            'chip_status' => SubscriptionStatus::Active,
            'chip_price' => 'price_basic_monthly',
            'quantity' => 1,
        ]);
    });

    $subscriptionId = (string) $subscription->getKey();

    $gateway = new ChipGateway([]);

    $resolved = OwnerContext::withOwner($ownerA, fn () => $gateway->retrieveSubscription($subscriptionId));
    $crossTenant = OwnerContext::withOwner($ownerB, fn () => $gateway->retrieveSubscription($subscriptionId));

    expect($resolved)->not->toBeNull()
        ->and($resolved?->id())->toBe($subscriptionId)
        ->and($crossTenant)->toBeNull();
});
