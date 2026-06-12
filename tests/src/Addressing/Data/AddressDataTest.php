<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;

it('maps line1 and line2', function (): void {
    $address = AddressData::from([
        'line1' => '123 Main St',
        'line2' => 'Apt 4B',
    ]);

    expect($address->line1)->toBe('123 Main St');
    expect($address->line2)->toBe('Apt 4B');
});

it('maps address_line_1 and address_line_2 aliases', function (): void {
    $address = AddressData::from([
        'address_line_1' => '123 Main St',
        'address_line_2' => 'Apt 4B',
    ]);

    expect($address->line1)->toBe('123 Main St');
    expect($address->line2)->toBe('Apt 4B');
});

it('maps street_address to line1', function (): void {
    $address = AddressData::from([
        'street_address' => '123 Main St',
    ]);

    expect($address->line1)->toBe('123 Main St');
});

it('maps postal_code to postcode', function (): void {
    $address = AddressData::from([
        'postal_code' => '50450',
    ]);

    expect($address->postcode)->toBe('50450');
});

it('maps zip_code to postcode', function (): void {
    $address = AddressData::from([
        'zip_code' => '10001',
    ]);

    expect($address->postcode)->toBe('10001');
});

it('maps postCode to postcode', function (): void {
    $address = AddressData::from([
        'postCode' => 'SW1A 1AA',
    ]);

    expect($address->postcode)->toBe('SW1A 1AA');
});

it('maps countryCode to countryCode', function (): void {
    $address = AddressData::from([
        'countryCode' => 'MY',
    ]);

    expect($address->countryCode)->toBe('MY');
});

it('maps country_code to countryCode', function (): void {
    $address = AddressData::from([
        'country_code' => 'MY',
    ]);

    expect($address->countryCode)->toBe('MY');
});

it('sets null for empty string values', function (): void {
    $address = AddressData::from([
        'line1' => '',
        'city' => '',
        'postcode' => '50450',
    ]);

    expect($address->line1)->toBeNull();
    expect($address->city)->toBeNull();
    expect($address->postcode)->toBe('50450');
});

it('converts to array and back', function (): void {
    $original = AddressData::from([
        'line1' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'countryCode' => 'MY',
    ]);

    $array = $original->toArray();
    $restored = AddressData::from($array);

    expect($restored->line1)->toBe('123 Main St');
    expect($restored->city)->toBe('Kuala Lumpur');
    expect($restored->countryCode)->toBe('MY');
});
