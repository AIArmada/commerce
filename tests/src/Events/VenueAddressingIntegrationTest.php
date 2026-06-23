<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Events\Models\Venue;
use Illuminate\Support\Str;

test('venue falls back to flat address columns when shared addressing is disabled', function (): void {
    config()->set('events.integrations.addressing_enabled', false);

    $venue = Venue::factory()->create([
        'line1' => 'Legacy Line 1',
        'line2' => 'Legacy Line 2',
        'city' => 'Legacy City',
        'state' => 'Legacy State',
        'postcode' => '50450',
        'country_code' => 'MY',
        'country' => 'Malaysia',
    ]);

    $address = $venue->getPrimaryAddressData();

    expect($address?->line1)->toBe('Legacy Line 1')
        ->and($address?->line2)->toBe('Legacy Line 2')
        ->and($address?->city)->toBe('Legacy City')
        ->and($address?->countryCode)->toBe('MY');
});

test('venue reads primary address data from the shared address relation when addressing is enabled', function (): void {
    config()->set('events.integrations.addressing_enabled', true);

    $venue = Venue::factory()->create([
        'line1' => 'Legacy Line 1',
        'line2' => 'Legacy Line 2',
        'city' => 'Legacy City',
        'state' => 'Legacy State',
        'postcode' => '50450',
        'country_code' => 'ZZ',
        'country' => 'Legacy Country',
    ]);

    $address = Address::create([
        'line1' => '123 Jalan Ampang',
        'line2' => 'Level 10',
        'city' => 'Kuala Lumpur',
        'state' => 'Wilayah Persekutuan',
        'postcode' => '50450',
        'country_code' => 'MY',
        'country' => 'Malaysia',
    ]);

    $venue->addresses()->attach($address->id, [
        'id' => (string) Str::orderedUuid(),
        'type' => 'primary',
        'is_primary' => true,
    ]);

    $addressData = $venue->getPrimaryAddressData();

    expect($addressData?->line1)->toBe('123 Jalan Ampang')
        ->and($addressData?->line2)->toBe('Level 10')
        ->and($addressData?->city)->toBe('Kuala Lumpur')
        ->and($addressData?->countryCode)->toBe('MY');
});
