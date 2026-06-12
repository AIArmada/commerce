<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Traits\HasAddresses;
use Illuminate\Database\Eloquent\Model;

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
