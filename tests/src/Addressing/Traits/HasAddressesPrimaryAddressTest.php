<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Support\AddressingTableResolver;
use AIArmada\Addressing\Traits\HasAddresses;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->host = new class extends Model
    {
        use HasAddresses;

        protected $table = 'test_owners';
    };
    $this->host->save();
});

it('does not leak cached addresses after an owner context switch', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    [$addressA, $addressB] = [
        OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
            'line1' => 'Owner A shipping',
            'country_code' => 'MY',
        ])),
        OwnerContext::withOwner($ownerB, fn (): Address => Address::query()->create([
            'line1' => 'Owner B shipping',
            'country_code' => 'MY',
        ])),
    ];

    OwnerContext::withOwner($ownerA, fn (): mixed => $this->host->attachAddress($addressA, type: 'shipping', isPrimary: true));
    OwnerContext::withOwner($ownerB, fn (): mixed => $this->host->attachAddress($addressB, type: 'shipping', isPrimary: true));

    OwnerContext::withOwner($ownerA, fn (): mixed => $this->host->load('addresses'));

    expect($this->host->relationLoaded('addresses'))->toBeTrue();

    $primaryInB = OwnerContext::withOwner($ownerB, fn (): ?Address => $this->host->primaryAddress('shipping'));

    expect($primaryInB?->getKey())->toBe($addressB->getKey());
});

it('returns no cached primary when the new owner has no addresses', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $addressA = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner A shipping',
        'country_code' => 'MY',
    ]));

    OwnerContext::withOwner($ownerA, fn (): mixed => $this->host->attachAddress($addressA, type: 'shipping', isPrimary: true));
    OwnerContext::withOwner($ownerA, fn (): mixed => $this->host->load('addresses'));

    expect(OwnerContext::withOwner($ownerB, fn (): ?Address => $this->host->primaryAddress('shipping')))->toBeNull()
        ->and(OwnerContext::withOwner($ownerB, fn (): ?Address => $this->host->primaryAddress()))->toBeNull();
});

it('resolves a requested type missing from a constrained preload', function (): void {
    $shipping = Address::query()->create(['line1' => 'Shipping', 'country_code' => 'MY']);
    $billing = Address::query()->create(['line1' => 'Billing', 'country_code' => 'MY']);

    $this->host->attachAddress($shipping, type: 'shipping', isPrimary: true);
    $this->host->attachAddress($billing, type: 'billing', isPrimary: true);

    $pivotTable = AddressingTableResolver::resolve('addressables');
    $this->host->load(['addresses' => fn ($query): mixed => $query->where("{$pivotTable}.type", 'billing')]);

    expect($this->host->getRelation('addresses'))->toHaveCount(1);

    expect($this->host->primaryAddress('shipping')?->getKey())->toBe($shipping->getKey())
        ->and($this->host->primaryAddress('billing')?->getKey())->toBe($billing->getKey());
});

it('serves a cache hit without querying', function (): void {
    $shipping = Address::query()->create(['line1' => 'Shipping', 'country_code' => 'MY']);
    $this->host->attachAddress($shipping, type: 'shipping', isPrimary: true);
    $this->host->load('addresses');

    DB::enableQueryLog();
    DB::flushQueryLog();

    $primary = $this->host->primaryAddress('shipping');

    expect($primary?->getKey())->toBe($shipping->getKey())
        ->and(DB::getQueryLog())->toBe([]);

    DB::disableQueryLog();
});

it('eager loads only the primary address subset', function (): void {
    $primary = Address::query()->create(['line1' => 'Primary shipping', 'country_code' => 'MY']);
    $secondary = Address::query()->create(['line1' => 'Secondary shipping', 'country_code' => 'MY']);
    $billing = Address::query()->create(['line1' => 'Primary billing', 'country_code' => 'MY']);

    $this->host->attachAddress($primary, type: 'shipping', isPrimary: true);
    $this->host->attachAddress($secondary, type: 'shipping', isPrimary: false);
    $this->host->attachAddress($billing, type: 'billing', isPrimary: true);

    $loaded = $this->host::query()->withPrimaryAddress()->whereKey($this->host->getKey())->firstOrFail();

    expect($loaded->getRelation('addresses')->pluck('id')->all())
        ->toHaveCount(2)
        ->toContain($primary->getKey(), $billing->getKey());

    $billingOnly = $this->host::query()->withPrimaryAddress('billing')->whereKey($this->host->getKey())->firstOrFail();

    expect($billingOnly->getRelation('addresses')->pluck('id')->all())->toBe([$billing->getKey()]);
});

it('does not leak partially loaded addresses to explicit global context', function (): void {
    $ownerA = User::factory()->create();

    $addressA = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner A shipping',
        'country_code' => 'MY',
    ]));

    OwnerContext::withOwner($ownerA, fn (): mixed => $this->host->attachAddress($addressA, type: 'shipping', isPrimary: true));
    OwnerContext::withOwner($ownerA, fn (): mixed => $this->host->load([
        'addresses' => fn ($query): mixed => $query->select('addresses.id', 'addresses.line1'),
    ]));

    $loaded = $this->host->getRelation('addresses')->firstOrFail();

    expect(array_key_exists('owner_type', $loaded->getAttributes()))->toBeFalse();

    DB::enableQueryLog();
    DB::flushQueryLog();

    $primary = OwnerContext::withOwner(null, fn (): ?Address => $this->host->primaryAddress('shipping'));

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($primary)->toBeNull()
        ->and($queries)->not->toBe([]);
});

it('does not treat partially loaded tenant rows as global when include_global is enabled', function (): void {
    config()->set('addressing.features.owner.include_global', true);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $addressA = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner A shipping',
        'country_code' => 'MY',
    ]));

    OwnerContext::withOwner($ownerA, fn (): mixed => $this->host->attachAddress($addressA, type: 'shipping', isPrimary: true));
    OwnerContext::withOwner($ownerA, fn (): mixed => $this->host->load([
        'addresses' => fn ($query): mixed => $query->select('addresses.id', 'addresses.line1'),
    ]));

    expect(OwnerContext::withOwner($ownerB, fn (): ?Address => $this->host->primaryAddress('shipping')))->toBeNull();
});

it('serves legitimate global rows from cache only when visible', function (): void {
    $ownerB = User::factory()->create();

    $global = OwnerContext::withOwner(null, fn (): Address => Address::query()->create([
        'line1' => 'Global shipping',
        'country_code' => 'MY',
    ]));

    OwnerContext::withOwner(null, fn (): mixed => $this->host->attachAddress($global, type: 'shipping', isPrimary: true));
    OwnerContext::withOwner(null, fn (): mixed => $this->host->load('addresses'));

    DB::enableQueryLog();
    DB::flushQueryLog();

    $primaryGlobal = OwnerContext::withOwner(null, fn (): ?Address => $this->host->primaryAddress('shipping'));

    expect($primaryGlobal?->getKey())->toBe($global->getKey())
        ->and(DB::getQueryLog())->toBe([]);

    DB::disableQueryLog();

    expect(OwnerContext::withOwner($ownerB, fn (): ?Address => $this->host->primaryAddress('shipping')))->toBeNull();

    config()->set('addressing.features.owner.include_global', true);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $primaryB = OwnerContext::withOwner($ownerB, fn (): ?Address => $this->host->primaryAddress('shipping'));

    expect($primaryB?->getKey())->toBe($global->getKey())
        ->and(DB::getQueryLog())->toBe([]);

    DB::disableQueryLog();
});

it('rejects cached rows when the pivot owner is not visible', function (): void {
    config()->set('addressing.features.owner.include_global', true);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $global = OwnerContext::withOwner(null, fn (): Address => Address::query()->create([
        'line1' => 'Global shipping',
        'country_code' => 'MY',
    ]));

    OwnerContext::withOwner(null, fn (): mixed => $this->host->attachAddress($global, type: 'shipping', isPrimary: true));

    DB::table(AddressingTableResolver::resolve('addressables'))
        ->where('address_id', $global->getKey())
        ->update(['owner_type' => $ownerA->getMorphClass(), 'owner_id' => $ownerA->getKey()]);

    OwnerContext::withOwner($ownerA, fn (): mixed => $this->host->load('addresses'));

    expect($this->host->getRelation('addresses'))->toHaveCount(1);

    expect(OwnerContext::withOwner($ownerB, fn (): ?Address => $this->host->primaryAddress('shipping')))->toBeNull();
});

it('ignores caller-mutated owner attributes on cached addresses', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $addressA = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner A shipping',
        'country_code' => 'MY',
    ]));

    OwnerContext::withOwner($ownerA, fn (): mixed => $this->host->attachAddress($addressA, type: 'shipping', isPrimary: true));
    OwnerContext::withOwner($ownerA, fn (): mixed => $this->host->load('addresses'));

    $loaded = $this->host->getRelation('addresses')->firstOrFail();
    $loaded->setAttribute('owner_type', null);
    $loaded->setAttribute('owner_id', null);
    $loaded->pivot->setAttribute('owner_type', null);
    $loaded->pivot->setAttribute('owner_id', null);

    expect(OwnerContext::withOwner(null, fn (): ?Address => $this->host->primaryAddress('shipping')))->toBeNull();

    config()->set('addressing.features.owner.include_global', true);

    expect(OwnerContext::withOwner($ownerB, fn (): ?Address => $this->host->primaryAddress('shipping')))->toBeNull();
});

it('suppresses include_global for cached addresses when overridden', function (): void {
    config()->set('addressing.features.owner.include_global', true);

    $ownerB = User::factory()->create();

    $global = OwnerContext::withOwner(null, fn (): Address => Address::query()->create([
        'line1' => 'Global shipping',
        'country_code' => 'MY',
    ]));

    OwnerContext::withOwner(null, fn (): mixed => $this->host->attachAddress($global, type: 'shipping', isPrimary: true));
    OwnerContext::withOwner(null, fn (): mixed => $this->host->load('addresses'));

    expect(OwnerContext::withOwner($ownerB, fn (): ?Address => $this->host->primaryAddress('shipping'))->getKey())
        ->toBe($global->getKey());

    $suppressed = OwnerScopeOverride::withoutIncludeGlobal(
        fn (): ?Address => OwnerContext::withOwner($ownerB, fn (): ?Address => $this->host->primaryAddress('shipping')),
    );

    expect($suppressed)->toBeNull();
});
