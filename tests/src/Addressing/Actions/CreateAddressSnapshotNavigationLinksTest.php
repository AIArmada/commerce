<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\AddressSnapshotWriter;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\Addressing\Features\AddressLifecycle\Models\AddressSnapshot;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    $this->action = app(AddressSnapshotWriter::class);

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

    $snapshot = AddressSnapshot::query()->findOrFail($this->action->execute($this->snapshotable->getMorphClass(), $this->snapshotable->getKey(), (string) $address->getKey(), reason: 'test'));

    expect($snapshot->google_maps_url)->toBe('https://maps.app.goo.gl/snapshot-test');
});

it('snapshot copies waze_url from address', function (): void {
    $address = Address::create([
        'line1' => '123 Main St',
        'country_code' => 'MY',
        'waze_url' => 'https://waze.com/ul/snapshot-test',
    ]);

    $snapshot = AddressSnapshot::query()->findOrFail($this->action->execute($this->snapshotable->getMorphClass(), $this->snapshotable->getKey(), (string) $address->getKey(), reason: 'test'));

    expect($snapshot->waze_url)->toBe('https://waze.com/ul/snapshot-test');
});

it('snapshot copies navigation_links from address', function (): void {
    $address = Address::create([
        'line1' => '123 Main St',
        'country_code' => 'MY',
        'navigation_links' => ['apple_maps' => ['url' => 'https://maps.apple.com/test']],
    ]);

    $snapshot = AddressSnapshot::query()->findOrFail($this->action->execute($this->snapshotable->getMorphClass(), $this->snapshotable->getKey(), (string) $address->getKey(), reason: 'test'));

    expect($snapshot->navigation_links)->toBe(['apple_maps' => ['url' => 'https://maps.apple.com/test']]);
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

    $snapshot = AddressSnapshot::query()->findOrFail($this->action->execute($this->snapshotable->getMorphClass(), $this->snapshotable->getKey(), $addressData, reason: 'test'));

    expect($snapshot->google_maps_url)->toBe('https://maps.app.goo.gl/data-test');
    expect($snapshot->waze_url)->toBe('https://waze.com/ul/data-test');
    expect($snapshot->navigation_links)->toBe(['grab' => ['url' => 'https://grab.com/directions']]);
});

it('snapshot copies google identifiers from AddressData', function (): void {
    $addressData = AddressData::from([
        'line1' => '123 Main St',
        'countryCode' => 'MY',
        'google_place_id' => 'ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4',
        'google_feature_id' => '0x31cc:0x8e9f',
        'google_cid' => '1234567890123456789',
        'google_entity_id' => '/g/abc123',
    ]);

    $snapshot = AddressSnapshot::query()->findOrFail($this->action->execute($this->snapshotable->getMorphClass(), $this->snapshotable->getKey(), $addressData, reason: 'test'));

    expect($snapshot->google_place_id)->toBe('ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4')
        ->and($snapshot->google_feature_id)->toBe('0x31cc:0x8e9f')
        ->and($snapshot->google_cid)->toBe('1234567890123456789')
        ->and($snapshot->google_entity_id)->toBe('/g/abc123');
});
