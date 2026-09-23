<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kyrgyzstan\KyrgyzstanAddressFormatter;
use AIArmada\Addressing\Geography\Kyrgyzstan\KyrgyzstanGeographyProvider;

it('formats Kyrgyz addresses with the postcode left of the locality', function (): void {
    $formatted = app(KyrgyzstanAddressFormatter::class)->format(AddressData::from([
        'line1' => '193, Avenue Chuy, apt. 28',
        'city' => 'BISHKEK',
        'postcode' => '720001',
        'country_code' => 'KG',
    ]));

    expect($formatted)->toBe("193, Avenue Chuy, apt. 28\n720001 BISHKEK\nKyrgyzstan");
});
it('formats rural Kyrgyz addresses with the region below the postcode line', function (): void {
    $formatted = app(KyrgyzstanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lenin Street 12',
        'city' => 'KARAKOL',
        'state' => 'Issyk-Kul',
        'postcode' => '721600',
        'country_code' => 'KG',
    ]));

    expect($formatted)->toBe("Lenin Street 12\n721600 KARAKOL\nIssyk-Kul\nKyrgyzstan");
});

it('ships 44 districts under regions with parent links', function (): void {
    $areas = app(KyrgyzstanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(44)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('kg:district:alamudun')->name)->toBe('Alamüdün')
        ->and($byId->get('kg:district:birinchi-may')->name)->toBe('Birinchi May')
        ->and($byId->get('kg:district:kara-suu')->name)->toBe('Kara-Suu');
});

it('labels tiers Oblus, Shaar and Raion', function (): void {
    $provider = app(KyrgyzstanGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Oblus', 'city' => 'Shaar', 'district' => 'Raion'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
