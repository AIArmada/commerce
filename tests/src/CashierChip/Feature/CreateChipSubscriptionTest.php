<?php

declare(strict_types=1);

use AIArmada\CashierChip\Data\BillingCycle;
use AIArmada\CashierChip\Features\Subscriptions\Api\CreateSubscription;
use AIArmada\CashierChip\Features\Subscriptions\Api\SubscriptionDraft;
use AIArmada\CashierChip\Features\Subscriptions\Models\Subscription;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\CommerceSupport\Data\RecordReference;

uses(CashierChipTestCase::class);

it('rejects empty prices at the subscription feature boundary', function (): void {
    expect(fn () => new SubscriptionDraft(
        billable: new RecordReference('user', 'test'),
        type: 'default',
        items: [],
        cycle: new BillingCycle,
    ))->toThrow(InvalidArgumentException::class, 'At least one price');
});

it('creates a subscription through the pure feature contract', function (): void {
    $user = $this->createUser(['chip_id' => 'cli_feature']);
    $reference = app(CreateSubscription::class)->create(new SubscriptionDraft(
        billable: new RecordReference($user->getMorphClass(), (string) $user->getKey()),
        type: 'default',
        items: ['price_test' => ['price' => 'price_test', 'unit_amount' => 1250, 'quantity' => 2]],
        cycle: new BillingCycle,
    ));

    $subscription = Subscription::query()->findOrFail($reference->id);

    expect($subscription->billable_id)->toBe((string) $user->getKey())
        ->and($subscription->items()->firstOrFail()->unit_amount)->toBe(1250)
        ->and($subscription->quantity)->toBe(2);
});

it('rejects overflowing subscription item totals', function (): void {
    $draft = new SubscriptionDraft(
        billable: new RecordReference('user', 'test'),
        type: 'default',
        items: ['price_test' => ['price' => 'price_test', 'unit_amount' => PHP_INT_MAX, 'quantity' => 2]],
        cycle: new BillingCycle,
    );

    expect(fn () => $draft->totalAmount())->toThrow(InvalidArgumentException::class, 'supported range');
});
