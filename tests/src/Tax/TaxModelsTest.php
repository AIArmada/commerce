<?php

declare(strict_types=1);

use AIArmada\Tax\Enums\ZoneType;
use AIArmada\Tax\Models\TaxZone;

describe('TaxZone Model', function (): void {
    describe('TaxZone Creation', function (): void {
        /* Country create folded into TaxZoneTest 'can create tax zone'. */

        it('can create a state-based tax zone', function (): void {
            $zone = TaxZone::create([
                'name' => 'California',
                'code' => 'US-CA-' . uniqid(),
                'type' => 'state',
                'countries' => ['US'],
                'states' => ['CA'],
                'is_active' => true,
            ]);

            expect($zone->type)->toBe(ZoneType::State)
                ->and($zone->states)->toContain('CA');
        });

        it('can create a postcode-based tax zone', function (): void {
            $zone = TaxZone::create([
                'name' => 'Central KL',
                'code' => 'MY-KL-CTR-' . uniqid(),
                'type' => 'postcode',
                'countries' => ['MY'],
                'postcodes' => ['50000-50999'],
                'is_active' => true,
            ]);

            expect($zone->type)->toBe(ZoneType::Postcode)
                ->and($zone->postcodes)->toContain('50000-50999');
        });
    });
});
