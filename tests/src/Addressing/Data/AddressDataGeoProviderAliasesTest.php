<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;

it('accepts latitude', function (): void {
    $data = AddressData::from(['latitude' => 3.1712]);
    expect($data->latitude)->toBe(3.1712);
});

it('ignores unknown geo keys', function (): void {
    $data = AddressData::from([
        'latitude_degrees' => 3.1712,
        'longitude_degrees' => 101.6678,
        'map_place_reference' => 'place-ref-123',
    ]);

    expect($data->latitude)->toBeNull()
        ->and($data->longitude)->toBeNull()
        ->and($data->googlePlaceId)->toBeNull();
});

it('accepts longitude', function (): void {
    $data = AddressData::from(['longitude' => 101.6678]);
    expect($data->longitude)->toBe(101.6678);
});

it('accepts formatted_address', function (): void {
    $data = AddressData::from(['formatted_address' => '123 Main St, KL']);
    expect($data->formatted)->toBe('123 Main St, KL');
});

it('accepts formattedAddress', function (): void {
    $data = AddressData::from(['formattedAddress' => '123 Main St, KL']);
    expect($data->formatted)->toBe('123 Main St, KL');
});

it('accepts google_place_id', function (): void {
    $data = AddressData::from(['google_place_id' => 'ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4']);
    expect($data->googlePlaceId)->toBe('ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4');
});

it('accepts googlePlaceId', function (): void {
    $data = AddressData::from(['googlePlaceId' => 'ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4']);
    expect($data->googlePlaceId)->toBe('ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4');
});

it('accepts place_id alias', function (): void {
    $data = AddressData::from(['place_id' => 'place-id-123']);
    expect($data->googlePlaceId)->toBe('place-id-123');
});

it('accepts placeId alias', function (): void {
    $data = AddressData::from(['placeId' => 'place-id-123']);
    expect($data->googlePlaceId)->toBe('place-id-123');
});

it('accepts google_feature_id', function (): void {
    $data = AddressData::from(['google_feature_id' => '0x31cc4c8e4a5b6c7d:0x8e9f0a1b2c3d4e5f']);
    expect($data->googleFeatureId)->toBe('0x31cc4c8e4a5b6c7d:0x8e9f0a1b2c3d4e5f');
});

it('accepts google_cid', function (): void {
    $data = AddressData::from(['google_cid' => '1234567890123456789']);
    expect($data->googleCid)->toBe('1234567890123456789');
});

it('accepts google_entity_id', function (): void {
    $data = AddressData::from(['google_entity_id' => '/g/abc123']);
    expect($data->googleEntityId)->toBe('/g/abc123');
});

it('trims google identifiers and persists them to model attributes', function (): void {
    $data = AddressData::from([
        'google_place_id' => '  ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4  ',
        'google_feature_id' => '  0x31cc:0x8e9f  ',
        'google_cid' => '  1234567890123456789  ',
        'google_entity_id' => '  /m/xyz  ',
    ]);

    expect($data->googlePlaceId)->toBe('ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4')
        ->and($data->googleFeatureId)->toBe('0x31cc:0x8e9f')
        ->and($data->googleCid)->toBe('1234567890123456789')
        ->and($data->googleEntityId)->toBe('/m/xyz');

    $attributes = $data->toModelAttributes();

    expect($attributes['google_place_id'])->toBe('ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4')
        ->and($attributes['google_feature_id'])->toBe('0x31cc:0x8e9f')
        ->and($attributes['google_cid'])->toBe('1234567890123456789')
        ->and($attributes['google_entity_id'])->toBe('/m/xyz');
});
