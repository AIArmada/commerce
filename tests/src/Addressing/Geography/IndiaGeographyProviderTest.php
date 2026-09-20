<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\India\IndiaAddressFormatter;
use AIArmada\Addressing\Geography\India\IndiaGeographyProvider;
use AIArmada\Addressing\Models\State;

function indiaCsvRows(): array
{
    $providerFile = (string) (new ReflectionClass(IndiaGeographyProvider::class))->getFileName();
    $lines = file(dirname($providerFile, 4) . '/resources/geography/india-address-areas.csv');
    $header = str_getcsv((string) array_shift($lines));

    // The CSV uses CRLF line endings; strip the carriage return str_getcsv keeps.
    return array_map(static fn (string $line): array => array_combine($header, str_getcsv(mb_rtrim($line, "\r\n"))), $lines);
}

it('defines a two-level administrative hierarchy down to districts', function (): void {
    $hierarchies = app(IndiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('district')
        ->and($hierarchies[0]->levels[1]->kind)->toBe('area')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('state')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('district');
});

it('seeds the 28 states plus the 8 union territories', function (): void {
    $country = $this->seedCountry('IN');

    app(IndiaGeographyProvider::class)->seed($country);

    expect(State::query()->where('country_id', $country->id)->count())->toBe(36)
        ->and(State::query()->where('country_id', $country->id)->where('code', 'TS')->exists())->toBeTrue()
        ->and(State::query()->where('country_id', $country->id)->where('code', 'TG')->exists())->toBeFalse();
});

it('maps the bundled state, union territory, and district rows', function (): void {
    $rows = indiaCsvRows();
    $byType = array_count_values(array_column($rows, 'type'));

    expect($rows)->toHaveCount(822)
        ->and($byType['state'] ?? 0)->toBe(28)
        ->and($byType['union_territory'] ?? 0)->toBe(8)
        ->and($byType['district'] ?? 0)->toBe(786);

    $byId = array_column($rows, null, 'source_id');
    $orphans = [];

    foreach ($rows as $row) {
        if ($row['parent_source_id'] !== '' && ! isset($byId[$row['parent_source_id']])) {
            $orphans[] = $row['source_id'];
        }
    }

    expect($orphans)->toBe([]);

    expect($byId['in:district:792']['name'])->toBe('Hansi')
        ->and($byId['in:district:792']['parent_source_id'])->toBe('in:state:haryana')
        ->and($byId['in:district:793']['name'])->toBe('Kushavati')
        ->and($byId['in:district:793']['parent_source_id'])->toBe('in:state:goa')
        ->and($byId['in:district:794']['name'])->toBe('Outer North')
        ->and($byId['in:district:794']['parent_source_id'])->toBe('in:union_territory:delhi')
        ->and(isset($byId['in:district:671']))->toBeFalse()
        ->and($byId['in:district:375']['name'])->toBe('Bilaspur')
        ->and($byId['in:district:375']['parent_source_id'])->toBe('in:state:chhattisgarh')
        ->and($byId['in:district:15']['name'])->toBe('Bilaspur')
        ->and($byId['in:district:15']['parent_source_id'])->toBe('in:state:himachal-pradesh')
        ->and($byId['in:district:599']['name'])->toBe('Mahe')
        ->and($byId['in:district:601']['name'])->toBe('Yanam')
        ->and($byId['in:district:601']['parent_source_id'])->toBe('in:union_territory:puducherry')
        ->and($byId['in:district:293']['name'])->toBe('Sribhumi');

    $first = app(IndiaGeographyProvider::class)->addressAreaSource()->areas()->first();

    expect($first->sourceId)->toBe('in:union_territory:andaman-and-nicobar-islands')
        ->and($first->name)->toBe('Andaman and Nicobar Islands')
        ->and($first->code)->toBe('AN')
        ->and($first->level)->toBe(1)
        ->and($first->parentSourceId)->toBeNull()
        ->and($first->source)->toBe(IndiaGeographyProvider::AREA_SOURCE);
});

it('exposes post-2011 aliases, roles, and relationships', function (): void {
    $country = $this->seedCountry('IN');
    $provider = app(IndiaGeographyProvider::class);

    $names = $provider->areaNames($country);

    expect($names)->toHaveCount(68)
        ->and($names['in:district:293'] ?? [])->toBe([['name' => 'Karimganj', 'name_type' => 'historic']])
        ->and($names['in:district:631'] ?? [])->toBe([['name' => 'Ramanagara', 'name_type' => 'historic']])
        ->and($names['in:district:758'] ?? [])->toBe([
            ['name' => 'Chumoukedima', 'name_type' => 'alternative'],
            ['name' => 'Chumukedima', 'name_type' => 'common'],
        ])
        ->and($names['in:district:608'] ?? [])->toBe([
            ['name' => 'Sahibzada Ajit Singh Nagar', 'name_type' => 'official'],
        ]);

    $roles = $provider->areaRoles($country);

    expect($roles)->toHaveCount(822)
        ->and($roles['in:district:792'] ?? [])->toBe([['role' => 'district', 'country_code' => 'IN', 'is_primary' => true]])
        ->and($roles['in:state:haryana'][0]['role'] ?? null)->toBe('state');

    $relationships = $provider->areaRelationships($country);

    expect($relationships['in:district:792'] ?? [])->toBe([[
        'parent_source_id' => 'in:state:haryana',
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
    ]])->and($relationships['in:district:15'][0]['parent_source_id'] ?? null)->toBe('in:state:himachal-pradesh');
});

it('formats Indian addresses with locality, state and postcode lines', function (): void {
    $formatted = app(IndiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '4, Amrita Shergill Road',
        'city' => 'New Delhi',
        'state' => 'Delhi',
        'postcode' => '110003',
        'country_code' => 'IN',
    ]));

    expect($formatted)->toBe("4, Amrita Shergill Road\nNew Delhi\nDelhi\n110003\nIndia");
});
