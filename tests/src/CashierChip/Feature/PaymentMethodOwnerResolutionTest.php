<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Features\Subscriptions\Models\Subscription;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\CommerceSupport\Data\RecordReference;
use Illuminate\Auth\Access\AuthorizationException;

uses(CashierChipTestCase::class);

beforeEach(function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);
    config()->set('cashier-chip.features.owner.auto_assign_on_create', true);
    config()->set('cashier-chip.features.owner.validate_billable_owner', true);
});

it('blocks payment method writes for a non-HasOwner billable outside the resolved customer scope', function (): void {
    $other = $this->createUser(['chip_id' => 'cli_other']);
    $owner = $this->createUser(['chip_id' => 'cli_owner']);

    $store = Cashier::paymentMethodStore();
    $subject = new RecordReference($other->getMorphClass(), (string) $other->getKey());

    expect(fn () => $store->saveForBillable($subject, 'tok_cross_tenant'))
        ->toThrow(AuthorizationException::class, 'Cross-tenant payment method write blocked');

    expect(fn () => $store->deleteForBillable($subject, 'tok_cross_tenant'))
        ->toThrow(AuthorizationException::class, 'Cross-tenant payment method write blocked');
});

it('blocks subscription creation for a non-HasOwner billable outside the resolved customer scope', function (): void {
    $other = $this->createUser(['chip_id' => 'cli_subscription_other']);
    $owner = $this->createUser(['chip_id' => 'cli_subscription_owner']);

    expect(fn () => Subscription::query()->create([
        'billable_type' => $other->getMorphClass(),
        'billable_id' => (string) $other->getKey(),
        'type' => 'default',
        'chip_id' => 'sub_cross_tenant',
        'chip_status' => 'active',
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
    ]))->toThrow(AuthorizationException::class, 'Cross-tenant subscription write blocked');
});

it('does not delete a remote token that has no scoped local authorization', function (): void {
    $owner = $this->createUser(['chip_id' => 'cli_delete_gate']);

    $token = $this->fakeChip->getFakeClient()->addRecurringToken('cli_delete_gate');
    $tokenId = $token['id'];

    expect(fn () => $owner->deletePaymentMethod($tokenId))
        ->toThrow(AuthorizationException::class, 'Payment method is not accessible');

    expect($this->fakeChip->getFakeClient()->getClientRecurringToken('cli_delete_gate', $tokenId))
        ->not->toBeNull();
});
