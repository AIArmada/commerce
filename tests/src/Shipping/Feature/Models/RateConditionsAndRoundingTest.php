<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Feature\Models;

use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Models\ShippingRate;
use AIArmada\Shipping\Models\ShippingZone;

function conditionedRate(array $attributes): ShippingRate
{
    $zone = ShippingZone::query()->create([
        'name' => 'Condition Zone',
        'code' => 'COND-ZONE',
        'type' => 'country',
        'countries' => ['MY'],
    ]);

    return ShippingRate::query()->create(array_merge([
        'zone_id' => $zone->getKey(),
        'method_code' => 'standard',
        'name' => 'Conditioned Rate',
        'calculation_type' => 'flat',
        'base_rate' => 500,
    ], $attributes));
}

describe('Shipping rate conditions and percentage rounding', function (): void {
    it('rounds percentage rates half-up instead of truncating', function (): void {
        $rate = conditionedRate([
            'calculation_type' => 'percentage',
            'per_unit_rate' => 5, // 0.05%
        ]);

        // 1500 * 5 / 10000 = 0.75 minor units -> rounds to 1, truncates to 0.
        expect($rate->calculateRate([new PackageData(weight: 1000)], 1500))->toBe(1);
    });
});
