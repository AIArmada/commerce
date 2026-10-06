<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\France\FranceGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('names the 973 region Guyane with French Guiana as the English alias', function (): void {
    $areas = app(FranceGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $names = app(FranceGeographyProvider::class)->areaNames(new AddressCountry);

    expect($areas->get('fr:region:french-guiana')->name)->toBe('Guyane')
        ->and($names['fr:region:french-guiana'][0])->toBe(['name' => 'French Guiana', 'name_type' => 'alternative']);
});

it('links every code to an existing department with exactly one primary', function (): void {
    $source = new CsvPostalCodeSource(
        'FR',
        __DIR__ . '/../../../../packages/addressing/resources/geography/france-postal-codes.csv',
        __DIR__ . '/../../../../packages/addressing/resources/geography/france-postal-code-areas.csv',
        'aiarmada.addressing.france'
    );

    $postcodes = $source->postalCodes()->collect();
    $areas = app(FranceGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // B13: 4,624 qualifier rows stripped to base codes (01014 9 → 01014),
    // 7 GN-artefact dual legs deleted, 93380 filled.
    expect($postcodes->pluck('code')->unique())->toHaveCount(20316)
        ->and($postcodes)->toHaveCount(20340)
        ->and($postcodes->pluck('areaSourceId')->every(fn ($id) => $areas->has($id)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;

    expect($byCode->every(fn ($legs) => $legs->where('isPrimary', true)->count() === 1))->toBeTrue();

    // Dual-leg exemplars: kept cross-department dual, collapsed single,
    // 69M retarget, Pierrefitte-merger fill.
    expect($byCode->get('01200')->count())->toBe(2)
        ->and($byCode->get('02160')->count())->toBe(1)
        ->and($byCode->get('69310')->firstWhere('isPrimary', true)->areaSourceId)->toBe('fr:department:lyon')
        ->and($byCode->get('93380')->firstWhere('isPrimary', true)->areaSourceId)->toBe('fr:department:seine-saint-denis');
});

it('seeds the Lyon split and hyphenated region names consistently', function (): void {
    expect($this->seedProviderConsistently(FranceGeographyProvider::class))->toBe([]);

    $areas = app(FranceGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // B13 tree cells: ISO hyphenated Grand Est, COG spaced Lyon rename,
    // straight-apostrophe Provence-Alpes-Côte-d'Azur.
    expect($areas->get('fr:region:grand-est')->name)->toBe('Grand Est')
        ->and($areas->get('fr:department:lyon')->name)->toBe('Métropole de Lyon')
        ->and($areas->get('fr:region:provence-alpes-cote-dazur')->name)->toBe("Provence-Alpes-Côte-d'Azur");
});
