<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Feature\Models;

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Models\ShippingZone;

function postcodeTestAddress(string $postcode): AddressData
{
    return new AddressData(
        name: 'Postcode Tester',
        phone: '+60123456789',
        line1: '1 Test Road',
        postcode: $postcode,
        country: 'MY',
    );
}

describe('Shipping zone postcode ranges', function (): void {
    it('compares variable-length numeric postcodes numerically', function (): void {
        $zone = ShippingZone::query()->create([
            'name' => 'Short Range Zone',
            'code' => 'SHORT-RANGE',
            'type' => 'postcode',
            'postcode_ranges' => [['from' => '1000', 'to' => '9999']],
        ]);

        expect($zone->matchesAddress(postcodeTestAddress('50000')))->toBeFalse()
            ->and($zone->matchesAddress(postcodeTestAddress('5000')))->toBeTrue();
    });

    it('keeps lexicographic comparison for alphanumeric postcodes', function (): void {
        $zone = ShippingZone::query()->create([
            'name' => 'Alpha Zone',
            'code' => 'ALPHA',
            'type' => 'postcode',
            'postcode_ranges' => [['from' => 'A100', 'to' => 'A200']],
        ]);

        expect($zone->matchesAddress(postcodeTestAddress('A150')))->toBeTrue()
            ->and($zone->matchesAddress(postcodeTestAddress('B150')))->toBeFalse();
    });
});
