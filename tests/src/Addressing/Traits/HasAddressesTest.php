<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\Addressable;
use AIArmada\Addressing\Traits\HasAddresses;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->model = new class extends Model
    {
        use HasAddresses;

        protected $table = 'test_owners';
    };
    $this->model->save();

    $this->address1 = Address::create(['line1' => 'Address 1', 'country_code' => 'MY']);
    $this->address2 = Address::create(['line1' => 'Address 2', 'country_code' => 'MY']);
});

it('attaches address to model', function (): void {
    $pivot = $this->model->attachAddress($this->address1, type: 'shipping');

    expect($this->model->addresses()->count())->toBe(1);
    expect($pivot->type)->toBe('shipping');
});

it('sets primary address', function (): void {
    $this->model->attachAddress($this->address1, type: 'primary');
    $this->model->setPrimaryAddress($this->address1, type: 'primary');

    $primary = $this->model->primaryAddress('primary');
    expect($primary)->not->toBeNull();
    expect($primary->id)->toBe($this->address1->id);
});

it('unsets old primary of same type', function (): void {
    $this->model->attachAddress($this->address1, type: 'primary', isPrimary: true);
    $this->model->attachAddress($this->address2, type: 'primary', isPrimary: false);

    $this->model->setPrimaryAddress($this->address2, type: 'primary');

    $primary = $this->model->primaryAddress('primary');
    expect($primary->id)->toBe($this->address2->id);
});

it('preserves primary of different type', function (): void {
    $this->model->attachAddress($this->address1, type: 'shipping', isPrimary: true);
    $this->model->attachAddress($this->address2, type: 'billing', isPrimary: true);

    $this->model->setPrimaryAddress($this->address2, type: 'billing');

    $shippingPrimary = $this->model->primaryAddress('shipping');
    expect($shippingPrimary->id)->toBe($this->address1->id);

    $billingPrimary = $this->model->primaryAddress('billing');
    expect($billingPrimary->id)->toBe($this->address2->id);
});

it('lists addresses of type', function (): void {
    $this->model->attachAddress($this->address1, type: 'shipping');
    $this->model->attachAddress($this->address2, type: 'billing');

    $shippingAddresses = $this->model->addressesOfType('shipping');
    expect($shippingAddresses)->toHaveCount(1);
    expect($shippingAddresses->first()->id)->toBe($this->address1->id);
});

it('returns only currently valid primary addresses', function (): void {
    $addressablesTable = config('addressing.tables.addressables', 'addressables');
    $now = CarbonImmutable::now();

    $this->model->attachAddress($this->address1, type: 'shipping', isPrimary: true);
    $this->model->attachAddress($this->address2, type: 'shipping', isPrimary: true);

    DB::table($addressablesTable)
        ->where('addressable_type', $this->model->getMorphClass())
        ->where('addressable_id', $this->model->getKey())
        ->where('address_id', $this->address1->id)
        ->update([
            'valid_from' => $now->subDay(),
            'valid_until' => $now->addDay(),
        ]);

    DB::table($addressablesTable)
        ->where('addressable_type', $this->model->getMorphClass())
        ->where('addressable_id', $this->model->getKey())
        ->where('address_id', $this->address2->id)
        ->update([
            'valid_from' => $now->addDay(),
            'valid_until' => null,
        ]);

    $primary = $this->model->primaryAddress('shipping');

    expect($primary)->not->toBeNull();
    expect($primary->id)->toBe($this->address1->id);
});

it('filters addressable pivots to those valid now', function (): void {
    $addressablesTable = config('addressing.tables.addressables', 'addressables');
    $now = CarbonImmutable::now();

    $this->model->attachAddress($this->address1, type: 'shipping', isPrimary: true);
    $this->model->attachAddress($this->address2, type: 'shipping', isPrimary: true);

    DB::table($addressablesTable)
        ->where('addressable_type', $this->model->getMorphClass())
        ->where('addressable_id', $this->model->getKey())
        ->where('address_id', $this->address1->id)
        ->update([
            'valid_from' => $now->subDay(),
            'valid_until' => $now->addDay(),
        ]);

    DB::table($addressablesTable)
        ->where('addressable_type', $this->model->getMorphClass())
        ->where('addressable_id', $this->model->getKey())
        ->where('address_id', $this->address2->id)
        ->update([
            'valid_from' => $now->addDay(),
            'valid_until' => null,
        ]);

    $validAddressIds = Addressable::query()->validNow()->pluck('address_id')->all();

    expect($validAddressIds)->toContain($this->address1->id);
    expect($validAddressIds)->not->toContain($this->address2->id);
});

it('returns all addresses of a type even when the relation is eager loaded with a constrained subset', function (): void {
    $this->model->attachAddress($this->address1, type: 'shipping', isPrimary: true);
    $this->model->attachAddress($this->address2, type: 'shipping', isPrimary: false);

    $this->model->load([
        'addresses' => function ($query): void {
            $query->where('addressables.is_primary', true);
        },
    ]);

    $shippingAddresses = $this->model->addressesOfType('shipping');

    expect($shippingAddresses)->toHaveCount(2);
    expect($shippingAddresses->pluck('id')->all())->toContain($this->address1->id);
    expect($shippingAddresses->pluck('id')->all())->toContain($this->address2->id);
});
