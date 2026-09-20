<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Nigeria\NigeriaAddressFormatter;
use AIArmada\Addressing\Geography\Nigeria\NigeriaGeographyProvider;
use AIArmada\Addressing\Models\State;

function nigeriaCsvRows(): array
{
    $providerFile = (string) (new ReflectionClass(NigeriaGeographyProvider::class))->getFileName();
    $lines = file(dirname($providerFile, 4) . '/resources/geography/nigeria-address-areas.csv');
    $header = str_getcsv((string) array_shift($lines));

    return array_map(static fn (string $line): array => array_combine($header, str_getcsv($line)), $lines);
}

it('defines a two-level administrative hierarchy down to LGAs', function (): void {
    $hierarchies = app(NigeriaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('lga')
        ->and($hierarchies[0]->levels[1]->kind)->toBe('area')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('state')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('lga');
});

it('seeds the 36 states plus the FCT', function (): void {
    $country = $this->seedCountry('NG');

    app(NigeriaGeographyProvider::class)->seed($country);

    expect(State::query()->where('country_id', $country->id)->count())->toBe(37)
        ->and(State::query()->where('country_id', $country->id)->where('code', 'FC')->value('name'))->toBe('Abuja Federal Capital Territory');
});

it('maps the bundled state, LGA, and area council rows', function (): void {
    $rows = nigeriaCsvRows();
    $byType = array_count_values(array_column($rows, 'type'));

    expect($rows)->toHaveCount(811)
        ->and($byType['state'] ?? 0)->toBe(37)
        ->and($byType['lga'] ?? 0)->toBe(768)
        ->and($byType['area_council'] ?? 0)->toBe(6);

    $byId = array_column($rows, null, 'source_id');
    $orphans = [];

    foreach ($rows as $row) {
        if ($row['parent_source_id'] !== '' && ! isset($byId[$row['parent_source_id']])) {
            $orphans[] = $row['source_id'];
        }
    }

    expect($orphans)->toBe([]);

    expect($byId['ng:lga:lagos:surulere']['name'])->toBe('Surulere')
        ->and($byId['ng:lga:lagos:surulere']['parent_source_id'])->toBe('ng:state:lagos')
        ->and($byId['ng:lga:oyo:surulere']['parent_source_id'])->toBe('ng:state:oyo')
        ->and($byId['ng:area_council:abuja-federal-capital-territory:gwagwalada']['type'])->toBe('area_council')
        ->and($byId['ng:area_council:abuja-federal-capital-territory:gwagwalada']['parent_source_id'])->toBe('ng:state:abuja-federal-capital-territory');

    $first = app(NigeriaGeographyProvider::class)->addressAreaSource()->areas()->first();

    expect($first->sourceId)->toBe('ng:state:abia')
        ->and($first->name)->toBe('Abia')
        ->and($first->code)->toBe('AB')
        ->and($first->level)->toBe(1)
        ->and($first->parentSourceId)->toBeNull()
        ->and($first->source)->toBe(NigeriaGeographyProvider::AREA_SOURCE);
});

it('exposes pre-2023 aliases, roles, and relationships', function (): void {
    $country = $this->seedCountry('NG');
    $provider = app(NigeriaGeographyProvider::class);

    $names = $provider->areaNames($country);

    expect($names)->toHaveCount(11)
        ->and($names['ng:lga:ebonyi:edda'] ?? [])->toBe([['name' => 'Afikpo South', 'name_type' => 'historic']])
        ->and($names['ng:lga:ekiti:aiyekire'] ?? [])->toBe([
            ['name' => 'Gbonyin', 'name_type' => 'common', 'is_preferred' => true],
            ['name' => 'Ayekire', 'name_type' => 'alternative'],
        ])
        ->and($names['ng:area_council:abuja-federal-capital-territory:abuja-municipal'] ?? [])->toBe([
            ['name' => 'Abuja Municipal Area Council', 'name_type' => 'official'],
            ['name' => 'AMAC', 'name_type' => 'abbreviation'],
        ]);

    $roles = $provider->areaRoles($country);

    expect($roles)->toHaveCount(811)
        ->and($roles['ng:lga:lagos:surulere'] ?? [])->toBe([['role' => 'lga', 'country_code' => 'NG', 'is_primary' => true]])
        ->and($roles['ng:area_council:abuja-federal-capital-territory:kwali'][0]['role'] ?? null)->toBe('lga');

    $relationships = $provider->areaRelationships($country);

    expect($relationships['ng:lga:lagos:surulere'] ?? [])->toBe([[
        'parent_source_id' => 'ng:state:lagos',
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
    ]])->and($relationships['ng:lga:oyo:surulere'][0]['parent_source_id'] ?? null)->toBe('ng:state:oyo');
});

it('formats Nigerian addresses with the postcode right and state below', function (): void {
    $formatted = app(NigeriaAddressFormatter::class)->format(AddressData::from([
        'line1' => '34 Alayande Cl',
        'city' => 'Mokola',
        'state' => 'OYO STATE',
        'postcode' => '200212',
        'country_code' => 'NG',
    ]));

    expect($formatted)->toBe("34 Alayande Cl\nMokola 200212\nOYO STATE\nNigeria");
});
