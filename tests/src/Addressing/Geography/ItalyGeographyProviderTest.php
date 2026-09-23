<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Italy\ItalyAddressFormatter;
use AIArmada\Addressing\Geography\Italy\ItalyGeographyProvider;

it('formats Italian addresses with the province abbreviation', function (): void {
    $formatted = app(ItalyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'VIALE EUROPA 22',
        'components' => ['province_code' => 'rm'],
        'city' => 'ROMA',
        'postcode' => '00122',
        'country_code' => 'IT',
    ]));

    expect($formatted)->toBe("VIALE EUROPA 22\n00122 ROMA RM\nItaly");
});

it('ships 109 provinces/metros/consortiums under regions with parent links', function (): void {
    $areas = app(ItalyGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['province', 'metropolitan_city', 'free_municipal_consortium', 'decentralization_entity', 'autonomous_province']);

    expect($l2)->toHaveCount(109)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('it:metropolitan_city:rome')->name)->toBe('Rome')
        ->and($byId->get('it:metropolitan_city:milan')->name)->toBe('Milan')
        ->and($byId->get('it:metropolitan_city:naples')->name)->toBe('Naples');
});

it('labels regions Regione with no per-state overrides', function (): void {
    $provider = app(ItalyGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Regione'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
