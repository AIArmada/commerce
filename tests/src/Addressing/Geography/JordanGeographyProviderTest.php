<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Jordan\JordanAddressFormatter;
use AIArmada\Addressing\Geography\Jordan\JordanGeographyProvider;
use AIArmada\Addressing\Models\State;

function jordanCsvRows(): array
{
    $providerFile = (string) (new ReflectionClass(JordanGeographyProvider::class))->getFileName();
    $lines = file(dirname($providerFile, 4) . '/resources/geography/jordan-address-areas.csv');
    $header = str_getcsv((string) array_shift($lines));

    return array_map(static fn (string $line): array => array_combine($header, str_getcsv($line)), $lines);
}

it('defines a two-level administrative hierarchy down to liwa', function (): void {
    $hierarchies = app(JordanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('governorate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('liwa')
        ->and($hierarchies[0]->levels[1]->kind)->toBe('area')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('governorate')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('liwa');
});

it('seeds the 12 governorates', function (): void {
    $country = $this->seedCountry('JO');

    app(JordanGeographyProvider::class)->seed($country);

    expect(State::query()->where('country_id', $country->id)->count())->toBe(12)
        ->and(State::query()->where('country_id', $country->id)->where('code', 'MN')->value('name'))->toBe("Ma'an");
});

it('maps the bundled governorate and liwa rows', function (): void {
    $rows = jordanCsvRows();
    $byType = array_count_values(array_column($rows, 'type'));

    expect($rows)->toHaveCount(63)
        ->and($byType['governorate'] ?? 0)->toBe(12)
        ->and($byType['liwa'] ?? 0)->toBe(51);

    $byId = array_column($rows, null, 'source_id');
    $orphans = [];

    foreach ($rows as $row) {
        if ($row['parent_source_id'] !== '' && ! isset($byId[$row['parent_source_id']])) {
            $orphans[] = $row['source_id'];
        }
    }

    expect($orphans)->toBe([]);

    $liwaPerParent = array_count_values(array_column(
        array_filter($rows, static fn (array $row): bool => $row['type'] === 'liwa'),
        'parent_source_id',
    ));

    expect($liwaPerParent)->toBe([
        'jo:governorate:ajloun' => 2,
        'jo:governorate:amman' => 9,
        'jo:governorate:aqaba' => 2,
        'jo:governorate:tafilah' => 3,
        'jo:governorate:zarqa' => 3,
        'jo:governorate:balqa' => 5,
        'jo:governorate:irbid' => 9,
        'jo:governorate:jerash' => 1,
        'jo:governorate:karak' => 7,
        'jo:governorate:mafraq' => 4,
        'jo:governorate:madaba' => 2,
        'jo:governorate:ma-an' => 4,
    ]);

    expect($byId['jo:liwa:amman:marka']['name'])->toBe('Marka')
        ->and($byId['jo:liwa:amman:marka']['parent_source_id'])->toBe('jo:governorate:amman')
        ->and($byId['jo:liwa:balqa:deir-alla']['parent_source_id'])->toBe('jo:governorate:balqa')
        ->and($byId['jo:liwa:ma-an:petra']['type'])->toBe('liwa')
        ->and($byId['jo:liwa:ma-an:petra']['parent_source_id'])->toBe('jo:governorate:ma-an')
        ->and($byId['jo:liwa:aqaba:quairah']['parent_source_id'])->toBe('jo:governorate:aqaba');

    $first = app(JordanGeographyProvider::class)->addressAreaSource()->areas()->first();

    expect($first->sourceId)->toBe('jo:governorate:ajloun')
        ->and($first->name)->toBe('Ajloun')
        ->and($first->code)->toBe('AJ')
        ->and($first->level)->toBe(1)
        ->and($first->parentSourceId)->toBeNull()
        ->and($first->source)->toBe(JordanGeographyProvider::AREA_SOURCE);
});

it('exposes transliteration aliases, roles, and relationships', function (): void {
    $country = $this->seedCountry('JO');
    $provider = app(JordanGeographyProvider::class);

    $names = $provider->areaNames($country);

    expect($names)->toHaveCount(19)
        ->and($names['jo:liwa:amman:quaismeh'] ?? [])->toBe([
            ['name' => 'Quwaysimah', 'name_type' => 'alternative'],
            ['name' => 'Al-Qwesmeh', 'name_type' => 'alternative'],
        ])
        ->and($names['jo:liwa:balqa:deir-alla'] ?? [])->toBe([['name' => 'Dair Alla', 'name_type' => 'official']])
        ->and($names['jo:liwa:ma-an:shobak'] ?? [])->toBe([['name' => 'Shoubak', 'name_type' => 'alternative']]);

    $roles = $provider->areaRoles($country);

    expect($roles)->toHaveCount(63)
        ->and($roles['jo:liwa:amman:marka'] ?? [])->toBe([['role' => 'liwa', 'country_code' => 'JO', 'is_primary' => true]])
        ->and($roles['jo:governorate:amman'][0]['role'] ?? null)->toBe('governorate');

    $relationships = $provider->areaRelationships($country);

    expect($relationships['jo:liwa:amman:marka'] ?? [])->toBe([[
        'parent_source_id' => 'jo:governorate:amman',
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
    ]])->and($relationships['jo:liwa:ma-an:petra'][0]['parent_source_id'] ?? null)->toBe('jo:governorate:ma-an');
});

it('formats Jordanian addresses with the postcode right of the locality', function (): void {
    $formatted = app(JordanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Al Mohazab Al Halabi',
        'city' => 'AMMAN',
        'postcode' => '11937',
        'country_code' => 'JO',
    ]));

    expect($formatted)->toBe("Al Mohazab Al Halabi\nAMMAN 11937\nJordan");
});
