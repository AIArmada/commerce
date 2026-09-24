<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Madagascar\MadagascarAddressFormatter;
use AIArmada\Addressing\Geography\Madagascar\MadagascarGeographyProvider;

it('formats Malagasy addresses with the postcode left of the town', function (): void {
    $formatted = app(MadagascarAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lot II M 85 D Antsahameva',
        'city' => 'TOAMASINA',
        'postcode' => '501',
        'country_code' => 'MG',
    ]));

    expect($formatted)->toBe("Lot II M 85 D Antsahameva\n501 TOAMASINA\nMadagascar");
});

it('ships 24 regions under provinces with parent links', function (): void {
    $areas = app(MadagascarGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'region');

    expect($l2)->toHaveCount(24)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('mg:region:analamanga')->name)->toBe('Analamanga')
        ->and($byId->get('mg:region:diana')->name)->toBe('Diana')
        ->and($byId->get('mg:region:ambatosoa')->name)->toBe('Ambatosoa');
});

it('labels tiers Faritany, Faritra and Distrika', function (): void {
    $provider = app(MadagascarGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['province' => 'Faritany', 'region' => 'Faritra', 'district' => 'Distrika'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});

it('ships 114 districts under regions with parent links', function (): void {
    $areas = app(MadagascarGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l3 = $areas->where('type', 'district');

    expect($l3)->toHaveCount(114)
        ->and($l3->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('mg:district:antananarivo-renivohitra')->name)->toBe('Antananarivo-Renivohitra')
        ->and($byId->get('mg:district:toamasina-i')->parentSourceId)->toBe('mg:region:atsinanana')
        ->and($byId->get('mg:district:maroantsetra')->parentSourceId)->toBe('mg:region:ambatosoa');
});
