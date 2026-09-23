<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\France\FranceAddressFormatter;
use AIArmada\Addressing\Geography\France\FranceGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats French addresses with the postcode left of the locality', function (): void {
    $formatted = app(FranceAddressFormatter::class)->format(AddressData::from([
        'line1' => '25 RUE DES FLEURS',
        'city' => 'LIBOURNE',
        'postcode' => '33500',
        'country_code' => 'FR',
    ]));

    expect($formatted)->toBe("25 RUE DES FLEURS\n33500 LIBOURNE\nFrance");
});

it('ships 102 departments under regions with parent links', function (): void {
    $areas = app(FranceGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'department');

    expect($l2)->toHaveCount(102)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('fr:department:paris')->name)->toBe('Paris')
        ->and($byId->get('fr:department:nord')->name)->toBe('Nord')
        ->and($byId->get('fr:department:bouches-du-rhone')->name)->toBe('Bouches-du-Rhône');
});

it('labels tiers Région and Département with no per-state overrides', function (): void {
    $provider = app(FranceGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['region' => 'Région', 'department' => 'Département'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});

it('names the 973 region Guyane with French Guiana as the English alias', function (): void {
    $areas = app(FranceGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $names = app(FranceGeographyProvider::class)->areaNames(new AddressCountry);

    expect($areas->get('fr:region:french-guiana')->name)->toBe('Guyane')
        ->and($names['fr:region:french-guiana'][0])->toBe(['name' => 'French Guiana', 'name_type' => 'alternative']);
});
