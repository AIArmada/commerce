<?php

declare(strict_types=1);

use AIArmada\Tax\Data\TaxResultData;
use AIArmada\Tax\Facades\Tax;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;

describe('Currency handling', function (): void {
    it('normalizes valid context currencies and falls back otherwise', function (): void {
        $zone = TaxZone::query()->create([
            'name' => 'Currency Zone',
            'code' => 'CURRENCY-ZONE',
            'countries' => ['MY'],
            'is_active' => true,
        ]);

        TaxRate::query()->create([
            'zone_id' => $zone->id,
            'tax_class' => 'standard',
            'name' => 'Currency Rate',
            'rate' => 600,
            'is_active' => true,
        ]);

        $lower = Tax::calculateTax(10000, 'standard', $zone->id, ['currency' => 'usd']);
        $invalid = Tax::calculateTax(10000, 'standard', $zone->id, ['currency' => 'XX']);
        $missing = Tax::calculateTax(10000, 'standard', $zone->id);

        expect($lower->currency)->toBe('USD')
            ->and($invalid->currency)->toBe('MYR')
            ->and($missing->currency)->toBe('MYR');
    });

    it('builds money values without throwing on unexpected currencies', function (): void {
        $valid = new TaxResultData(
            taxAmount: 600,
            rateId: 'rate-1',
            rateName: 'Rate',
            ratePercentage: 600,
            zoneId: 'zone-1',
            zoneName: 'Zone',
            currency: 'usd',
        );

        expect($valid->getMoney()->getCurrency()->getCurrency())->toBe('USD');

        $invalid = new TaxResultData(
            taxAmount: 600,
            rateId: 'rate-1',
            rateName: 'Rate',
            ratePercentage: 600,
            zoneId: 'zone-1',
            zoneName: 'Zone',
            currency: 'not-a-currency!!',
        );

        expect($invalid->getMoney()->getCurrency()->getCurrency())->toBe('MYR');
    });
});
