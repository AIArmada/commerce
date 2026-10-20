<?php

declare(strict_types=1);

use AIArmada\Cashier\Concerns\Billable as UnifiedCashierBillable;
use AIArmada\Cashier\Contracts\BillableContract as UnifiedBillableContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Testing\FakeChipClient;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User as CashierChipUser;

uses(CashierChipTestCase::class);

/*
|--------------------------------------------------------------------------
| CHK-06: the generic cashier-to-CHIP path applies provider idempotency
|--------------------------------------------------------------------------
|
| The unified `ChipGateway` delegates to the real cashier-chip billable
| charge, which reads the top-level `idempotency_key` option and stages it
| on the CHIP purchase builder. These regressions drive that exact
| delegation (not the direct cashier-chip adapter) against the keyed fake
| client and assert one provider purchase per attempt key.
|
*/

class Chk06ChipGatewayBillable extends CashierChipUser implements UnifiedBillableContract
{
    use UnifiedCashierBillable;

    protected $table = 'users';

    /**
     * Everything the CHIP adapter calls (charge, chipName/Email/Phone,
     * preferredCurrency, createOrGetChipCustomer, createSetupPurchase) is
     * inherited from the cashier-chip billable trait; the unified contract
     * only needs the Stripe hooks and customer accessors stubbed.
     */
    public function customerName(): ?string
    {
        return $this->chipName();
    }

    public function customerEmail(): ?string
    {
        return $this->chipEmail();
    }

    public function customerPhone(): ?string
    {
        return $this->chipPhone();
    }

    /**
     * @return array<string, mixed>
     */
    public function customerAddress(): array
    {
        return $this->chipAddress();
    }

    public function preferredLocale(): ?string
    {
        return null;
    }

    public function stripeId(): ?string
    {
        return null;
    }

    public function asStripeCustomer(array $expand = []): mixed
    {
        throw new RuntimeException('The CHIP gateway must not call Stripe hooks.');
    }

    public function createOrGetStripeCustomer(array $options = [], array $requestOptions = []): mixed
    {
        throw new RuntimeException('The CHIP gateway must not call Stripe hooks.');
    }

    public function updateStripeCustomer(array $options = []): mixed
    {
        throw new RuntimeException('The CHIP gateway must not call Stripe hooks.');
    }

    public function syncStripeCustomerDetails(array $options = []): mixed
    {
        throw new RuntimeException('The CHIP gateway must not call Stripe hooks.');
    }

    public function createSetupIntent(array $options = []): mixed
    {
        throw new RuntimeException('The CHIP gateway must not call Stripe hooks.');
    }

    public function billingPortalUrl(?string $returnUrl = null, array $options = []): string
    {
        throw new RuntimeException('The CHIP gateway must not call Stripe hooks.');
    }
}

function chk06ChipBillable(): Chk06ChipGatewayBillable
{
    $billable = new Chk06ChipGatewayBillable;
    $billable->forceFill([
        'name' => 'CHK-06 CHIP Billable',
        'email' => 'chk06-chip-' . uniqid() . '@example.com',
    ]);
    $billable->save();

    return $billable;
}

it('applies the top-level key through the generic cashier CHIP gateway to one purchase', function (): void {
    $fakeClient = new FakeChipClient;
    Cashier::fake($fakeClient);

    $gateway = new ChipGateway([]);
    $user = chk06ChipBillable();

    $first = $gateway->charge($user, 1000, null, [
        'idempotency_key' => 'chk06-chip-key-1',
        'currency' => 'MYR',
    ]);
    $second = $gateway->charge($user, 1000, null, [
        'idempotency_key' => 'chk06-chip-key-1',
        'currency' => 'MYR',
    ]);

    expect($first)->toBeInstanceOf(PaymentContract::class)
        ->and($second->id())->toBe($first->id())
        ->and($fakeClient->getPurchases())->toHaveCount(1);

    $purchases = array_values($fakeClient->getPurchases());
    $purchase = $purchases[0];

    expect($purchase['idempotency_key'])->toBe('chk06-chip-key-1')
        ->and($purchase['purchase']['currency'])->toBe('MYR')
        ->and($purchase['purchase']['total'])->toBe(1000);
});

it('uses a new purchase for a separate attempt key', function (): void {
    $fakeClient = new FakeChipClient;
    Cashier::fake($fakeClient);

    $gateway = new ChipGateway([]);
    $user = chk06ChipBillable();

    $first = $gateway->charge($user, 1000, null, [
        'idempotency_key' => 'chk06-chip-key-1',
        'currency' => 'MYR',
    ]);
    $second = $gateway->charge($user, 1000, null, [
        'idempotency_key' => 'chk06-chip-key-2',
        'currency' => 'MYR',
    ]);

    expect($second->id())->not->toBe($first->id())
        ->and($fakeClient->getPurchases())->toHaveCount(2);

    $keys = array_column(array_values($fakeClient->getPurchases()), 'idempotency_key');

    expect($keys)->toContain('chk06-chip-key-1')
        ->and($keys)->toContain('chk06-chip-key-2');
});

it('keeps request metadata as attribution alongside the top-level key', function (): void {
    $fakeClient = new FakeChipClient;
    Cashier::fake($fakeClient);

    $gateway = new ChipGateway([]);
    $user = chk06ChipBillable();

    $gateway->charge($user, 1000, null, [
        'idempotency_key' => 'chk06-chip-key-3',
        'currency' => 'MYR',
        'reference' => 'checkout:session-1',
        'metadata' => [
            'checkout_session_id' => 'checkout:session-1',
            'checkout_attempt' => 1,
        ],
    ]);

    $purchases = array_values($fakeClient->getPurchases());
    $purchase = $purchases[0];

    expect($purchase['idempotency_key'])->toBe('chk06-chip-key-3')
        ->and($purchase['reference'])->toBe('checkout:session-1')
        ->and($purchase['purchase']['metadata']['checkout_session_id'])->toBe('checkout:session-1')
        ->and($purchase['purchase']['metadata']['checkout_attempt'])->toBe(1)
        ->and($purchase['purchase']['metadata']['billable_id'])->toBeString();
});
