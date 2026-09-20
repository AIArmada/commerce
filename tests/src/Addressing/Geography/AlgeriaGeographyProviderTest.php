<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Algeria\AlgeriaAddressFormatter;
use AIArmada\Addressing\Geography\Algeria\AlgeriaGeographyProvider;
use AIArmada\Addressing\Models\State;

function algeriaCsvRows(): array
{
    $providerFile = (string) (new ReflectionClass(AlgeriaGeographyProvider::class))->getFileName();
    $lines = file(dirname($providerFile, 4) . '/resources/geography/algeria-address-areas.csv');
    $header = str_getcsv((string) array_shift($lines));

    return array_map(static fn (string $line): array => array_combine($header, str_getcsv($line)), $lines);
}

it('defines a two-level administrative hierarchy down to dairas', function (): void {
    $hierarchies = app(AlgeriaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('wilaya')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('daira')
        ->and($hierarchies[0]->levels[1]->kind)->toBe('area')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('wilaya')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('daira');
});

it('seeds the 69 wilayas', function (): void {
    $country = $this->seedCountry('DZ');

    app(AlgeriaGeographyProvider::class)->seed($country);

    expect(State::query()->where('country_id', $country->id)->count())->toBe(69)
        ->and(State::query()->where('country_id', $country->id)->where('code', '49')->value('name'))->toBe('Timimoun');
});

it('maps the bundled wilaya and daira rows', function (): void {
    $rows = algeriaCsvRows();
    $byType = array_count_values(array_column($rows, 'type'));

    expect($rows)->toHaveCount(617)
        ->and($byType['wilaya'] ?? 0)->toBe(69)
        ->and($byType['daira'] ?? 0)->toBe(548);

    $byId = array_column($rows, null, 'source_id');
    $orphans = [];

    foreach ($rows as $row) {
        if ($row['parent_source_id'] !== '' && ! isset($byId[$row['parent_source_id']])) {
            $orphans[] = $row['source_id'];
        }
    }

    expect($orphans)->toBe([]);

    expect($byId['dz:daira:alger:bab-el-oued']['name'])->toBe('Bab El Oued')
        ->and($byId['dz:daira:alger:bab-el-oued']['parent_source_id'])->toBe('dz:wilaya:alger')
        ->and($byId['dz:daira:el-aricha:el-aricha']['parent_source_id'])->toBe('dz:wilaya:el-aricha')
        ->and($byId['dz:daira:tlemcen:sebdou']['parent_source_id'])->toBe('dz:wilaya:tlemcen')
        ->and($byId['dz:daira:ouargla:el-borma']['parent_source_id'])->toBe('dz:wilaya:ouargla')
        ->and($byId['dz:daira:el-menia:el-meniaa']['name'])->toBe('El Meniaa')
        ->and($byId['dz:daira:bordj-bou-arreridj:mansoura']['name'])->toBe('Mansoura')
        ->and($byId['dz:daira:ghardaia:mansoura']['parent_source_id'])->toBe('dz:wilaya:ghardaia')
        ->and($byId['dz:daira:oran:ain-el-turk']['type'])->toBe('daira');

    $first = app(AlgeriaGeographyProvider::class)->addressAreaSource()->areas()->first();

    expect($first->sourceId)->toBe('dz:wilaya:adrar')
        ->and($first->name)->toBe('Adrar')
        ->and($first->code)->toBe('01')
        ->and($first->level)->toBe(1)
        ->and($first->parentSourceId)->toBeNull()
        ->and($first->source)->toBe(AlgeriaGeographyProvider::AREA_SOURCE);
});

it('exposes name variants, roles, and relationships', function (): void {
    $country = $this->seedCountry('DZ');
    $provider = app(AlgeriaGeographyProvider::class);

    $names = $provider->areaNames($country);

    expect($names)->toHaveCount(24)
        ->and($names['dz:daira:bou-saada:bou-saada'] ?? [])->toBe([
            ['name' => 'Bousaada', 'name_type' => 'alternative'],
            ['name' => 'Bou Saâda', 'name_type' => 'common'],
        ])
        ->and($names['dz:daira:el-menia:el-meniaa'] ?? [])->toBe([
            ['name' => 'El Menia', 'name_type' => 'common'],
        ])
        ->and($names['dz:daira:el-tarf:ben-m-hidi'] ?? [])->toBe([
            ['name' => 'Ben Mehidi', 'name_type' => 'alternative'],
        ]);

    $roles = $provider->areaRoles($country);

    expect($roles)->toHaveCount(617)
        ->and($roles['dz:daira:alger:bab-el-oued'] ?? [])->toBe([['role' => 'daira', 'country_code' => 'DZ', 'is_primary' => true]])
        ->and($roles['dz:wilaya:alger'][0]['role'] ?? null)->toBe('wilaya');

    $relationships = $provider->areaRelationships($country);

    expect($relationships['dz:daira:el-aricha:el-aricha'] ?? [])->toBe([[
        'parent_source_id' => 'dz:wilaya:el-aricha',
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
    ]])->and($relationships['dz:daira:ghardaia:mansoura'][0]['parent_source_id'] ?? null)->toBe('dz:wilaya:ghardaia');
});

it('formats Algerian addresses with the postcode left of the locality', function (): void {
    $formatted = app(AlgeriaAddressFormatter::class)->format(AddressData::from([
        'line1' => "2, rue de l'Indépendance",
        'city' => 'ALGIERS',
        'postcode' => '16027',
        'country_code' => 'DZ',
    ]));

    expect($formatted)->toBe("2, rue de l'Indépendance\n16027 ALGIERS\nAlgeria");
});
