<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;

it('accepts latitude', function (): void {
    $data = AddressData::from(['latitude' => 3.1712]);
    expect($data->latitude)->toBe(3.1712);
});

it('does not accept the removed lat alias', function (): void {
    $data = AddressData::from(['lat' => 3.1712]);
    expect($data->latitude)->toBeNull();
});

it('accepts longitude', function (): void {
    $data = AddressData::from(['longitude' => 101.6678]);
    expect($data->longitude)->toBe(101.6678);
});

it('does not accept the removed lng alias', function (): void {
    $data = AddressData::from(['lng' => 101.6678]);
    expect($data->longitude)->toBeNull();
});

it('does not accept the removed lon alias', function (): void {
    $data = AddressData::from(['lon' => 101.6678]);
    expect($data->longitude)->toBeNull();
});

it('accepts formatted_address', function (): void {
    $data = AddressData::from(['formatted_address' => '123 Main St, KL']);
    expect($data->formatted)->toBe('123 Main St, KL');
});

it('accepts formattedAddress', function (): void {
    $data = AddressData::from(['formattedAddress' => '123 Main St, KL']);
    expect($data->formatted)->toBe('123 Main St, KL');
});

it('accepts provider', function (): void {
    $data = AddressData::from(['provider' => 'google']);
    expect($data->provider)->toBe('google');
});

it('accepts provider_place_id', function (): void {
    $data = AddressData::from(['provider_place_id' => 'ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4']);
    expect($data->providerPlaceId)->toBe('ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4');
});

it('accepts providerPlaceId', function (): void {
    $data = AddressData::from(['providerPlaceId' => 'ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4']);
    expect($data->providerPlaceId)->toBe('ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4');
});

it('accepts place_id alias', function (): void {
    $data = AddressData::from(['place_id' => 'place-id-123']);
    expect($data->providerPlaceId)->toBe('place-id-123');
});

it('accepts placeId alias', function (): void {
    $data = AddressData::from(['placeId' => 'place-id-123']);
    expect($data->providerPlaceId)->toBe('place-id-123');
});

it('does not accept the removed google_place_id alias', function (): void {
    $data = AddressData::from(['google_place_id' => 'google-place-123']);
    expect($data->providerPlaceId)->toBeNull();
});

it('does not accept the removed googlePlaceId alias', function (): void {
    $data = AddressData::from(['googlePlaceId' => 'google-place-123']);
    expect($data->providerPlaceId)->toBeNull();
});

it('keeps existing address field aliases working with canonical geo fields', function (): void {
    $data = AddressData::from([
        'address_line_1' => '123 Main St',
        'postal_code' => '50450',
        'latitude' => 3.1712,
        'longitude' => 101.6678,
    ]);
    expect($data->line1)->toBe('123 Main St');
    expect($data->postcode)->toBe('50450');
    expect($data->latitude)->toBe(3.1712);
    expect($data->longitude)->toBe(101.6678);
});
