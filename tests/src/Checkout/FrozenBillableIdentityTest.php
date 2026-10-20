<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\GatewayManager;
use AIArmada\CashierChip\Billing\Billable as ChipBillableTrait;
use AIArmada\CashierChip\Billing\Cashier as ChipCashier;
use AIArmada\CashierChip\Contracts\BillableContract as ChipBillableContract;
use AIArmada\Checkout\Data\PaymentAttemptClaim;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Enums\PaymentClaimStatus;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Integrations\Payment\CashierChipProcessor;
use AIArmada\Checkout\Integrations\Payment\CashierProcessor;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Support\PaymentRequestBuilder;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Facades\Chip;
use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\ChipBillableUser;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| CHK-11 / CHK-12 / CHK-10: frozen billable identity
|--------------------------------------------------------------------------
|
| Native CashierChip billables are a supported boundary without the unified
| Cashier contract. A frozen local model is resolved through the morph map,
| owner-scoped, and then the adapter validates its own contract. The provider
| customer identity and client fields are frozen with the request so a
| mutated model cannot change a replay.
|
*/

abstract class Chk11NativeBillableBase extends Model implements ChipBillableContract
{
    use ChipBillableTrait;
    use HasUuids;

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    public function chipId(): ?string
    {
        // Read the raw attribute: ManagesCustomer maps `chip_id` through
        // getChipIdAttribute(), which calls back into chipId().
        $value = $this->attributes['chip_id'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function hasChipId(): bool
    {
        return $this->chipId() !== null;
    }

    public function owner(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'owner_type', 'owner_id');
    }

    public function hasOwner(): bool
    {
        return is_string($this->getAttribute('owner_type'))
            && $this->getAttribute('owner_type') !== ''
            && $this->getAttribute('owner_id') !== null;
    }

    public function belongsToOwner(Model $owner): bool
    {
        return $this->getAttribute('owner_type') === $owner->getMorphClass()
            && (string) $this->getAttribute('owner_id') === (string) $owner->getKey();
    }
}

final class Chk11NativeBillable extends Chk11NativeBillableBase
{
    protected $table = 'chk11_native_billables';
}

final class Chk12NativeBillable extends Chk11NativeBillableBase
{
    protected $table = 'chk12_native_billables';
}

final class Chk12RemappedBillable extends Chk11NativeBillableBase
{
    protected $table = 'chk12_remapped_billables';
}

beforeEach(function (): void {
    Schema::create('chk11_native_billables', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->nullableUuidMorphs('owner');
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('chip_id')->nullable();
        $table->timestamps();
    });

    foreach (['chk12_native_billables', 'chk12_remapped_billables'] as $name) {
        Schema::create($name, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('chip_id')->nullable();
            $table->timestamps();
        });
    }
});

afterEach(function (): void {
    ChipCashier::unfake();
    Relation::morphMap([], false);
    Relation::requireMorphMap(false);
});

function chk11Session(?Model $billable = null, ?Model $customer = null): CheckoutSession
{
    $session = new CheckoutSession;
    $session->setAttribute('id', (string) Str::uuid());
    $session->setAttribute('billing_data', [
        'name' => 'Frozen Customer',
        'email' => 'frozen@example.com',
        'phone' => '+60123456789',
        'line1' => '1 Frozen Street',
        'city' => 'Frozen City',
        'postcode' => '50000',
        'country_code' => 'MY',
    ]);
    $session->setRelation('billable', $billable);
    $session->setRelation('customer', $customer);

    return $session;
}

function chk11Request(Model $billable, string $idempotencyKey = 'chk11-session:attempt:1'): PaymentRequest
{
    $type = $billable::class;
    $id = (string) $billable->getKey();
    $customerId = method_exists($billable, 'chipId') ? $billable->chipId() : null;

    $request = new PaymentRequest(
        amount: 2500,
        currency: 'MYR',
        gateway: 'cashier-chip',
        description: 'Frozen native order',
        customerEmail: 'frozen@example.com',
        customerName: 'Frozen Customer',
        successUrl: 'https://example.test/success',
        failureUrl: 'https://example.test/failure',
        cancelUrl: 'https://example.test/cancel',
        provider: 'chip',
        idempotencyKey: $idempotencyKey,
        billableType: $type,
        billableId: $id,
        client: [
            'email' => 'frozen@example.com',
            'full_name' => 'Frozen Customer',
            'phone' => '+60123456789',
            'street_address' => '1 Frozen Street',
            'city' => 'Frozen City',
            'state' => null,
            'zip_code' => '50000',
            'country' => 'MY',
        ],
        clientFrozen: true,
        providerCustomerId: $customerId,
        providerCustomerFrozen: true,
    );

    return $request;
}

/** @return array<string, mixed> */
function chk11OnlyPurchase(): array
{
    $purchases = array_values(ChipCashier::getFake()?->getFakeClient()->getPurchases() ?? []);

    expect($purchases)->toHaveCount(1);

    /** @var array<string, mixed> $purchase */
    $purchase = $purchases[0];

    return $purchase;
}

it('charges an actual native CashierChip billable through the frozen request', function (): void {
    $fake = ChipCashier::fake();

    $billable = Chk11NativeBillable::query()->create([
        'name' => 'Frozen Native',
        'email' => 'frozen-native@example.com',
        'phone' => '+60111111111',
        'chip_id' => 'cli_frozen_chk11',
    ]);

    $session = chk11Session($billable);
    $request = chk11Request($billable);

    // Reassociate the session after the request was frozen. Only the frozen
    // identity may be charged.
    $replacement = Chk11NativeBillable::query()->create([
        'name' => 'Replacement Native',
        'email' => 'replacement-native@example.com',
        'phone' => '+60222222222',
        'chip_id' => 'cli_replacement_chk11',
    ]);
    $session->setRelation('billable', $replacement);

    // Mutate the same persisted customer after the request was frozen.
    $billable->forceFill([
        'name' => 'Mutated Native',
        'email' => 'mutated-native@example.com',
        'phone' => '+60999999999',
        'chip_id' => 'cli_mutated_chk11',
    ])->save();

    expect($billable)->toBeInstanceOf(ChipBillableContract::class)
        ->and($billable)->not->toBeInstanceOf(BillableContract::class)
        ->and($replacement)->not->toBeInstanceOf(BillableContract::class);

    $first = app(CashierChipProcessor::class)->createPayment($session, $request);
    $second = app(CashierChipProcessor::class)->createPayment($session, $request);

    // A genuinely new attempt may use the updated provider identity with a
    // new key; it must not reuse or overwrite the first attempt's payload.
    $replacementRequest = chk11Request($billable, 'chk11-session:attempt:2');
    $third = app(CashierChipProcessor::class)->createPayment($session, $replacementRequest);

    expect($first->status)->toBe(PaymentStatus::Pending)
        ->and($first->paymentId)->not->toBeNull()
        ->and($second->paymentId)->toBe($first->paymentId)
        ->and($third->status)->toBe(PaymentStatus::Pending)
        ->and($third->paymentId)->not->toBe($first->paymentId);

    $purchases = array_values($fake->getFakeClient()->getPurchases());

    expect($purchases)->toHaveCount(2)
        ->and($purchases[0]['client_id'])->toBe('cli_frozen_chk11')
        ->and($purchases[0]['id'])->toBe($first->paymentId)
        ->and($purchases[1]['client_id'])->toBe('cli_mutated_chk11')
        ->and($purchases[1]['id'])->toBe($third->paymentId);
});

it('keeps frozen native client details when the provider customer id is null', function (): void {
    ChipCashier::fake();

    $billable = Chk11NativeBillable::query()->create([
        'name' => 'Live Client Name',
        'email' => 'live-client@example.com',
        'phone' => '+60888888888',
        'chip_id' => null,
    ]);

    $session = chk11Session($billable);
    $request = chk11Request($billable, 'chk10-native-null:attempt:1');

    OwnerContext::withOwner(null, static function () use ($billable): void {
        $billable->forceFill([
            'name' => 'Mutated Client Name',
            'email' => 'mutated-client@example.com',
            'phone' => '+60777777777',
        ])->save();
    });

    $result = app(CashierChipProcessor::class)->createPayment($session, $request);

    expect($result->status)->toBe(PaymentStatus::Pending);

    $purchase = chk11OnlyPurchase();

    expect($purchase['client_id'])->toBeNull()
        ->and($purchase['client']['email'])->toBe('frozen@example.com')
        ->and($purchase['client']['full_name'])->toBe('Frozen Customer')
        ->and($purchase['client']['phone'])->toBe('+60123456789')
        ->and($purchase['client']['email'])->not->toBe('mutated-client@example.com');
});

it('keeps an explicitly frozen guest a guest when the session now has a native billable', function (): void {
    $billable = Chk11NativeBillable::query()->create([
        'name' => 'Live Native',
        'email' => 'live-native@example.com',
        'phone' => '+60122222222',
        'chip_id' => 'cli_live_guest',
    ]);

    $session = chk11Session($billable);

    $request = new PaymentRequest(
        amount: 2500,
        currency: 'MYR',
        gateway: 'cashier-chip',
        description: 'Frozen guest order',
        successUrl: 'https://example.test/success',
        failureUrl: 'https://example.test/failure',
        cancelUrl: 'https://example.test/cancel',
        provider: 'chip',
        idempotencyKey: 'chk11-guest:attempt:1',
        billableType: null,
        billableId: null,
        client: [
            'email' => 'guest@example.com',
            'full_name' => 'Frozen Guest',
            'phone' => null,
            'street_address' => null,
            'city' => null,
            'state' => null,
            'zip_code' => null,
            'country' => 'MY',
        ],
        clientFrozen: true,
        providerCustomerId: null,
        providerCustomerFrozen: true,
        billableFrozen: true,
    );

    $captured = null;

    Chip::shouldReceive('createPurchase')
        ->once()
        ->withArgs(function (array $payload, string $key) use (&$captured): bool {
            $captured = $payload;

            return $key === 'chk11-guest:attempt:1';
        })
        ->andReturn(PurchaseData::from([
            'id' => 'pur_frozen_guest',
            'status' => 'created',
            'checkout_url' => 'https://gate.example.test/pur_frozen_guest',
            'client' => ['email' => 'guest@example.com'],
            'purchase' => ['total' => 2500, 'currency' => 'MYR', 'products' => []],
        ]));

    $result = app(CashierChipProcessor::class)->createPayment($session, $request);

    expect($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->paymentId)->toBe('pur_frozen_guest')
        ->and($captured)->toBeArray()
        ->and($captured['client']['email'])->toBe('guest@example.com')
        ->and($captured)->not->toHaveKey('client_id')
        ->and($captured['client']['email'])->not->toBe('live-native@example.com');
});

it('charges a frozen alias request through the morph map', function (): void {
    ChipCashier::fake();

    Relation::morphMap(['chk12_direct_alias' => Chk12NativeBillable::class], false);

    $billable = Chk12NativeBillable::query()->create([
        'name' => 'Direct Alias Native',
        'email' => 'direct-alias@example.com',
        'chip_id' => 'cli_direct_alias_chk12',
    ]);

    $request = chk11Request($billable, 'chk12-direct-alias:attempt:1');
    $request->billableType = 'chk12_direct_alias';

    $session = chk11Session($billable);
    $first = app(CashierChipProcessor::class)->createPayment($session, $request);
    $replay = app(CashierChipProcessor::class)->createPayment($session, $request);

    expect($first->status)->toBe(PaymentStatus::Pending)
        ->and($replay->paymentId)->toBe($first->paymentId);

    $purchase = chk11OnlyPurchase();

    expect($purchase['client_id'])->toBe('cli_direct_alias_chk12');
});

it('rejects unknown and non-model frozen aliases', function (): void {
    Relation::morphMap(['chk12_not_a_model' => stdClass::class], false);

    $billable = Chk11NativeBillable::query()->create([
        'name' => 'Bad Alias Native',
        'email' => 'bad-alias@example.com',
        'chip_id' => 'cli_bad_alias_chk12',
    ]);

    $unknown = chk11Request($billable, 'chk12-unknown-alias:attempt:1');
    $unknown->billableType = 'chk12_missing_alias';

    $nonModel = chk11Request($billable, 'chk12-non-model-alias:attempt:1');
    $nonModel->billableType = 'chk12_not_a_model';

    $unknownResult = app(CashierChipProcessor::class)->createPayment(chk11Session($billable), $unknown);
    $nonModelResult = app(CashierChipProcessor::class)->createPayment(chk11Session($billable), $nonModel);

    expect($unknownResult->status)->toBe(PaymentStatus::Failed)
        ->and($unknownResult->message)->toContain('frozen billable')
        ->and($nonModelResult->status)->toBe(PaymentStatus::Failed)
        ->and($nonModelResult->message)->toContain('frozen billable')
        ->and(ChipCashier::getFake()?->getFakeClient()->getPurchases())->toBeEmpty();
});

it('resolves a frozen morph alias for the unified cashier adapter consistently', function (): void {
    Relation::morphMap(['chk12_unified_alias' => ChipBillableUser::class], false);
    Relation::requireMorphMap();

    $billable = ChipBillableUser::query()->create([
        'name' => 'Unified Alias',
        'email' => 'unified-alias@example.com',
        'password' => bcrypt('secret'),
    ]);

    $snapshot = [
        'amount' => 2500,
        'currency' => 'MYR',
        'gateway' => 'cashier',
        'provider' => 'chip',
        'billable_type' => 'chk12_unified_alias',
        'billable_id' => (string) $billable->getKey(),
        'client_frozen' => true,
        'provider_customer_frozen' => true,
    ];

    $session = new CheckoutSession;
    $session->setAttribute('id', (string) Str::uuid());
    $session->setAttribute('payment_data', [
        'payment_attempt' => ['request' => $snapshot],
    ]);

    expect(app(CashierProcessor::class)->isAvailable($session))->toBeTrue();

    $payment = Mockery::mock(PaymentContract::class);
    $payment->shouldReceive('toArray')->andReturn([
        'id' => 'pay_unified_alias',
        'status' => 'succeeded',
        'currency' => 'MYR',
        'raw_amount' => 2500,
    ]);
    $payment->shouldReceive('id')->andReturn('pay_unified_alias');
    $payment->shouldReceive('redirectUrl')->andReturn(null);
    $payment->shouldReceive('status')->andReturn('succeeded');
    $payment->shouldReceive('rawAmount')->andReturn(2500);
    $payment->shouldReceive('currency')->andReturn('MYR');
    $payment->shouldReceive('isSucceeded')->andReturn(true);

    $gateway = Mockery::mock(GatewayContract::class);
    $gateway->shouldReceive('name')->andReturn('chip');
    $gateway->shouldReceive('charge')
        ->twice()
        ->with(
            Mockery::on(static fn (mixed $candidate): bool => $candidate instanceof ChipBillableUser
                && (string) $candidate->getKey() === (string) $billable->getKey()),
            2500,
            null,
            Mockery::type('array'),
        )
        ->andReturn($payment);

    $manager = Mockery::mock(GatewayManager::class);
    $manager->shouldReceive('gateway')->twice()->with('chip')->andReturn($gateway);
    app()->instance(GatewayManager::class, $manager);

    $request = PaymentRequest::fromSnapshot($snapshot);
    $first = app(CashierProcessor::class)->createPayment($session, $request);
    $replay = app(CashierProcessor::class)->createPayment($session, $request);

    expect(app(CashierProcessor::class)->isAvailable($session))->toBeTrue()
        ->and($first->status)->toBe(PaymentStatus::Completed)
        ->and($replay->paymentId)->toBe($first->paymentId);
});

it('fails closed when the frozen native billable no longer exists', function (): void {
    $billable = Chk11NativeBillable::query()->create([
        'name' => 'Deleted Native',
        'email' => 'deleted-native@example.com',
        'chip_id' => 'cli_deleted_chk11',
    ]);

    $request = chk11Request($billable);
    $session = chk11Session($billable);
    $billable->delete();

    $result = app(CashierChipProcessor::class)->createPayment($session, $request);

    expect($result->status)->toBe(PaymentStatus::Failed)
        ->and($result->message)->toContain('frozen billable');
});

it('fails closed when the frozen native billable belongs to another owner', function (): void {
    $owner = Chk11NativeBillable::query()->create([
        'name' => 'Owner',
        'email' => 'owner@example.com',
        'chip_id' => 'cli_owner_chk11',
    ]);

    $foreign = Chk11NativeBillable::query()->create([
        'name' => 'Foreign Native',
        'email' => 'foreign@example.com',
        'chip_id' => 'cli_foreign_chk11',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) Str::uuid(),
    ]);

    $session = chk11Session($foreign);
    $session->setAttribute('owner_type', $owner->getMorphClass());
    $session->setAttribute('owner_id', (string) $owner->getKey());

    $result = app(CashierChipProcessor::class)->createPayment($session, chk11Request($foreign));

    expect($result->status)->toBe(PaymentStatus::Failed)
        ->and($result->message)->toContain('frozen billable');
});

it('charges a frozen native billable that belongs to the session owner', function (): void {
    ChipCashier::fake();

    $owner = Chk11NativeBillable::query()->create([
        'name' => 'Owner',
        'email' => 'owner@example.com',
        'chip_id' => 'cli_owner_ok',
    ]);

    $billable = Chk11NativeBillable::query()->create([
        'name' => 'Owned Native',
        'email' => 'owned@example.com',
        'chip_id' => 'cli_owned_ok',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
    ]);

    $session = chk11Session($billable);
    $session->setAttribute('owner_type', $owner->getMorphClass());
    $session->setAttribute('owner_id', (string) $owner->getKey());

    $result = app(CashierChipProcessor::class)->createPayment($session, chk11Request($billable));

    expect($result->status)->toBe(PaymentStatus::Pending);

    $purchase = chk11OnlyPurchase();

    expect($purchase['client_id'])->toBe('cli_owned_ok');
});

it('freezes the authorized concrete class instead of a morph alias', function (): void {
    Relation::morphMap(['chk12_billable' => Chk12NativeBillable::class], false);

    $billable = Chk12NativeBillable::query()->create([
        'name' => 'Alias Native',
        'email' => 'alias@example.com',
        'chip_id' => 'cli_alias_chk12',
    ]);

    $session = new CheckoutSession;
    $session->setAttribute('id', (string) Str::uuid());
    $session->setAttribute('billable_type', 'chk12_billable');
    $session->setAttribute('billable_id', (string) $billable->getKey());
    $session->setRelation('billable', null);

    $claim = new PaymentAttemptClaim(
        status: PaymentClaimStatus::Claimed,
        number: 1,
        gateway: 'cashier-chip',
        provider: 'chip',
        amount: 2500,
        currency: 'MYR',
        idempotencyKey: 'chk12-session:attempt:1',
    );

    $request = (new PaymentRequestBuilder)->build($session, $claim);

    expect($request->billableType)->toBe(Chk12NativeBillable::class)
        ->and($request->billableId)->toBe((string) $billable->getKey())
        ->and($request->metadata['billable_type'])->toBe(Chk12NativeBillable::class);
});

it('does not let an alias remap redirect a frozen billable charge', function (): void {
    ChipCashier::fake();

    Relation::morphMap(['chk12_billable' => Chk12NativeBillable::class], false);

    $original = Chk12NativeBillable::query()->create([
        'name' => 'Original Native',
        'email' => 'original@example.com',
        'chip_id' => 'cli_original_chk12',
    ]);

    $session = chk11Session($original);
    $request = chk11Request($original, 'chk12-remap:attempt:1');

    // The alias now points at a different concrete model that has a row with
    // the same key but a different provider customer id.
    Chk12RemappedBillable::query()->create([
        'id' => (string) $original->getKey(),
        'name' => 'Remapped Native',
        'email' => 'remapped@example.com',
        'chip_id' => 'cli_remapped_chk12',
    ]);

    Relation::morphMap(['chk12_billable' => Chk12RemappedBillable::class], false);

    $result = app(CashierChipProcessor::class)->createPayment($session, $request);

    expect($result->status)->toBe(PaymentStatus::Pending);

    $purchase = chk11OnlyPurchase();

    expect($purchase['client_id'])->toBe('cli_original_chk12')
        ->and($purchase['client_id'])->not->toBe('cli_remapped_chk12');
});

it('resolves an enforced morph map without reinterpreting the frozen class', function (): void {
    ChipCashier::fake();

    Relation::morphMap(['chk12_billable' => Chk12NativeBillable::class], false);
    Relation::requireMorphMap();

    $billable = Chk12NativeBillable::query()->create([
        'name' => 'Enforced Native',
        'email' => 'enforced@example.com',
        'chip_id' => 'cli_enforced_chk12',
    ]);

    $request = chk11Request($billable, 'chk12-enforced:attempt:1');

    $result = app(CashierChipProcessor::class)->createPayment(chk11Session($billable), $request);

    expect($result->status)->toBe(PaymentStatus::Pending);

    $purchase = chk11OnlyPurchase();

    expect($purchase['client_id'])->toBe('cli_enforced_chk12');

    // Remapping the alias while the morph map is enforced must not redirect
    // the frozen concrete class, and an unnamed class must fail closed.
    Relation::morphMap(['chk12_billable' => Chk12RemappedBillable::class], false);

    $replay = app(CashierChipProcessor::class)->createPayment(
        chk11Session($billable),
        chk11Request($billable, 'chk12-enforced:attempt:2'),
    );

    expect($replay->status)->not->toBe(PaymentStatus::Pending)
        ->and(ChipCashier::getFake()?->getFakeClient()->getPurchases())->toHaveCount(1);
});
