<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Support\DestinationAddressValidator;

function shippableTestDestination(array $overrides = []): AddressData
{
    $args = array_merge([
        'name' => 'Aina Rahman',
        'phone' => '+60123456789',
        'line1' => '1 Jalan Ampang',
        'postcode' => '68000',
        'country' => 'MY',
        'city' => 'Ampang',
        'state' => 'Selangor',
    ], $overrides);

    return new AddressData(...$args);
}

it('passes complete destinations', function (): void {
    expect(app(DestinationAddressValidator::class)->validate(shippableTestDestination()))->toBe([]);
});

it('reports missing country-required fields', function (): void {
    $violations = app(DestinationAddressValidator::class)->validate(shippableTestDestination(['city' => null]));

    expect($violations)->toHaveKey('city');
});

it('reports malformed postcodes', function (): void {
    $violations = app(DestinationAddressValidator::class)->validate(shippableTestDestination(['postcode' => 'ABC']));

    expect($violations)->toHaveKey('postcode');
});
