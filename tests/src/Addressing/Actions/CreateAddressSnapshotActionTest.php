<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\CreateAddressSnapshotAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressSnapshot;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    $this->action = app(CreateAddressSnapshotAction::class);
});

it('snapshots address data from AddressData', function (): void {
    $snapshotable = new class extends Model
    {
        protected $table = 'test_models';
    };
    $snapshotable->save();

    $addressData = AddressData::from([
        'line1' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'postcode' => '50450',
        'countryCode' => 'MY',
    ]);

    $snapshot = $this->action->execute($snapshotable, $addressData, reason: 'test');

    expect($snapshot)->toBeInstanceOf(AddressSnapshot::class);
    expect($snapshot->line1)->toBe('123 Main St');
    expect($snapshot->city)->toBe('Kuala Lumpur');
    expect($snapshot->postcode)->toBe('50450');
    expect($snapshot->country_code)->toBe('MY');
    expect($snapshot->reason)->toBe('test');
    expect($snapshot->address_id)->toBeNull();
});

it('snapshots address from Address model', function (): void {
    $snapshotable = new class extends Model
    {
        protected $table = 'test_models';
    };
    $snapshotable->save();

    $address = Address::create([
        'line1' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'postcode' => '50450',
        'country_code' => 'MY',
        'provider' => 'google',
        'provider_place_id' => 'ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4',
    ]);

    $snapshot = $this->action->execute($snapshotable, $address, reason: 'order_placed');

    expect($snapshot->line1)->toBe('123 Main St');
    expect($snapshot->city)->toBe('Kuala Lumpur');
    expect($snapshot->address_id)->toBe($address->id);
    expect($snapshot->provider)->toBe('google');
    expect($snapshot->provider_place_id)->toBe('ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4');
});

it('snapshot copies latitude and longitude from address', function (): void {
    $snapshotable = new class extends Model
    {
        protected $table = 'test_models';
    };
    $snapshotable->save();

    $address = Address::create([
        'line1' => '123 Main St',
        'country_code' => 'MY',
        'latitude' => 3.1712,
        'longitude' => 101.6678,
    ]);

    $snapshot = $this->action->execute($snapshotable, $address, reason: 'test');

    expect($snapshot->latitude)->toBe(3.1712);
    expect($snapshot->longitude)->toBe(101.6678);
});

it('snapshot remains unchanged when original address changes', function (): void {
    $snapshotable = new class extends Model
    {
        protected $table = 'test_models';
    };
    $snapshotable->save();

    $address = Address::create([
        'line1' => 'Original Line',
        'city' => 'Original City',
        'postcode' => '12345',
        'country_code' => 'MY',
        'provider' => 'google',
        'provider_place_id' => 'ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4',
    ]);

    $snapshot = $this->action->execute($snapshotable, $address, reason: 'order_placed');

    $address->update([
        'line1' => 'Changed Line',
        'city' => 'Changed City',
        'provider' => 'here',
        'provider_place_id' => 'new-place-id',
    ]);

    $snapshot->refresh();

    expect($snapshot->line1)->toBe('Original Line');
    expect($snapshot->city)->toBe('Original City');
    expect($snapshot->provider)->toBe('google');
    expect($snapshot->provider_place_id)->toBe('ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4');
});
