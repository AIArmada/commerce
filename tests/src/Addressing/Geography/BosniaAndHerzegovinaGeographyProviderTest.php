<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\BosniaAndHerzegovina\BosniaAndHerzegovinaAddressFormatter;
use AIArmada\Addressing\Geography\BosniaAndHerzegovina\BosniaAndHerzegovinaGeographyProvider;

it('formats Bosnian addresses with the postcode left of the locality', function (): void {
    $formatted = app(BosniaAndHerzegovinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Semira Fraste E6/6',
        'city' => 'SARAJEVO',
        'postcode' => '71000',
        'country_code' => 'BA',
    ]));

    expect($formatted)->toBe("Semira Fraste E6/6\n71000 SARAJEVO\nBosnia and Herzegovina");
});
it('formats Bosnian rural addresses with the numberless street line', function (): void {
    $formatted = app(BosniaAndHerzegovinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Sapna BB',
        'city' => 'SAPNA',
        'postcode' => '75411',
        'country_code' => 'BA',
    ]));

    expect($formatted)->toBe("Sapna BB\n75411 SAPNA\nBosnia and Herzegovina");
});

it('ships 143 municipalitys under entitys with parent links', function (): void {
    $areas = app(BosniaAndHerzegovinaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(143)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ba:municipality:banja-luka')->name)->toBe('Banja Luka')
        ->and($byId->get('ba:municipality:mostar')->name)->toBe('Mostar')
        ->and($byId->get('ba:municipality:bihac')->name)->toBe('Bihać');
});

it('labels tiers Entitet, Distrikt and Općina with an Opština override for Srpska', function (): void {
    $provider = app(BosniaAndHerzegovinaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['entity' => 'Entitet', 'district' => 'Distrikt', 'municipality' => 'Općina'])
        ->and($provider->stateAreaTypeLabels())->toBe([
            ['state_code' => 'SRP', 'type_labels' => ['municipality' => 'Opština']],
        ]);
});
