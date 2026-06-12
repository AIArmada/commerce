<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\CreateAddressSnapshotAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Models\Address;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    $this->action = app(CreateAddressSnapshotAction::class);

    $this->snapshotable = new class extends Model
    {
        protected $table = 'test_models';
    };
    $this->snapshotable->save();
});

it('snapshot copies google_maps_url from address', function (): void {
    $address = Address::create([
        'line1' => '123 Main St',
        'country_code' => 'MY',
        'google_maps_url' => 'https://maps.app.goo.gl/snapshot-test',
    ]);

    $snapshot = $this->action->execute($this->snapshotable, $address, reason: 'test');

    expect($snapshot->google_maps_url)->toBe('https://maps.app.goo.gl/snapshot-test');
});

it('snapshot copies waze_url from address', function (): void {
    $address = Address::create([
        'line1' => '123 Main St',
        'country_code' => 'MY',
        'waze_url' => 'https://waze.com/ul/snapshot-test',
    ]);

    $snapshot = $this->action->execute($this->snapshotable, $address, reason: 'test');

    expect($snapshot->waze_url)->toBe('https://waze.com/ul/snapshot-test');
});

it('snapshot copies navigation_links from address', function (): void {
    $address = Address::create([
        'line1' => '123 Main St',
        'country_code' => 'MY',
        'navigation_links' => ['apple_maps' => ['url' => 'https://maps.apple.com/test']],
    ]);

    $snapshot = $this->action->execute($this->snapshotable, $address, reason: 'test');

    expect($snapshot->navigation_links)->toBe(['apple_maps' => ['url' => 'https://maps.apple.com/test']]);
});

it('changing source address does not mutate existing snapshot', function (): void {
    $address = Address::create([
        'line1' => 'Original Line',
        'country_code' => 'MY',
        'google_maps_url' => 'https://maps.app.goo.gl/original',
    ]);

    $snapshot = $this->action->execute($this->snapshotable, $address, reason: 'test');

    $address->update(['google_maps_url' => 'https://maps.app.goo.gl/changed']);

    $snapshot->refresh();

    expect($snapshot->google_maps_url)->toBe('https://maps.app.goo.gl/original');
});

it('snapshot copies nav links from AddressData', function (): void {
    $addressData = AddressData::from([
        'line1' => '123 Main St',
        'city' => 'KL',
        'countryCode' => 'MY',
        'google_maps_url' => 'https://maps.app.goo.gl/data-test',
        'waze_url' => 'https://waze.com/ul/data-test',
        'navigation_links' => ['grab' => ['url' => 'https://grab.com/directions']],
    ]);

    $snapshot = $this->action->execute($this->snapshotable, $addressData, reason: 'test');

    expect($snapshot->google_maps_url)->toBe('https://maps.app.goo.gl/data-test');
    expect($snapshot->waze_url)->toBe('https://waze.com/ul/data-test');
    expect($snapshot->navigation_links)->toBe(['grab' => ['url' => 'https://grab.com/directions']]);
});

it('snapshot copies provider from address', function (): void {
    $address = Address::create([
        'line1' => '123 Main St',
        'country_code' => 'MY',
        'provider' => 'google',
    ]);

    $snapshot = $this->action->execute($this->snapshotable, $address, reason: 'test');

    expect($snapshot->provider)->toBe('google');
});

it('snapshot copies provider_place_id from address', function (): void {
    $address = Address::create([
        'line1' => '123 Main St',
        'country_code' => 'MY',
        'provider_place_id' => 'ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4',
    ]);

    $snapshot = $this->action->execute($this->snapshotable, $address, reason: 'test');

    expect($snapshot->provider_place_id)->toBe('ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4');
});

it('snapshot copies provider and provider_place_id from AddressData', function (): void {
    $addressData = AddressData::from([
        'line1' => '123 Main St',
        'countryCode' => 'MY',
        'provider' => 'google',
        'provider_place_id' => 'ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4',
    ]);

    $snapshot = $this->action->execute($this->snapshotable, $addressData, reason: 'test');

    expect($snapshot->provider)->toBe('google');
    expect($snapshot->provider_place_id)->toBe('ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4');
});
