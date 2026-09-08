<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

uses(CashierChipTestCase::class);

/**
 * ADVERSARY PROOF — Attack 3: `Cashier::findBillable()` resolves another
 * tenant's billable with no owner check.
 *
 * The webhook-hardened twin `findBillableForWebhook()` fails closed when
 * owner mode is enabled and no owner is resolved; plain `findBillable()`
 * runs the same directory lookup with no owner argument at all, so any
 * entrypoint that accepts a gateway customer id (`ChipGateway::findBillable`
 * public API, `Payment::customer()`, listeners with owner mode off) crosses
 * the tenant boundary. Control assertion first: the hardened twin returns
 * null in the same context.
 *
 * No package logic is mocked; only config + the owner resolver binding.
 */
it('refuses to resolve a foreign billable without an owner context', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);

    $victim = $this->createUser([
        'name' => 'Victim Tenant User',
        'email' => 'victim-tenant-adv@example.com',
        'chip_id' => 'chip_cus_adv_victim',
    ]);

    // Attacker context: no owner can be resolved (e.g. system/webhook path
    // outside a session owner scope).
    OwnerContext::flushState();
    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    // Control: the hardened twin fails closed here.
    expect(Cashier::findBillableForWebhook('chip_cus_adv_victim'))->toBeNull();

    // Break: the plain resolver hands back the victim's billable anyway.
    $resolved = Cashier::findBillable('chip_cus_adv_victim');

    expect($resolved)->toBeNull('findBillable() resolved a foreign billable with no owner context.');
    expect($resolved?->getKey())->not->toBe((string) $victim->getKey());
});
