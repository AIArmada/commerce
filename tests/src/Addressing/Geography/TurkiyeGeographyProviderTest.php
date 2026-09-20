<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Turkiye\TurkiyeAddressFormatter;
use AIArmada\Addressing\Geography\Turkiye\TurkiyeGeographyProvider;
use AIArmada\Addressing\Models\State;

function turkiyeCsvRows(): array
{
    $providerFile = (string) (new ReflectionClass(TurkiyeGeographyProvider::class))->getFileName();
    $lines = file(dirname($providerFile, 4) . '/resources/geography/turkiye-address-areas.csv');
    $header = str_getcsv((string) array_shift($lines));

    return array_map(static fn (string $line): array => array_combine($header, str_getcsv($line)), $lines);
}

it('defines a two-level administrative hierarchy down to districts', function (): void {
    $hierarchies = app(TurkiyeGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('district')
        ->and($hierarchies[0]->levels[1]->kind)->toBe('area')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('province')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('district');
});

it('seeds the 81 provinces', function (): void {
    $country = $this->seedCountry('TR');

    app(TurkiyeGeographyProvider::class)->seed($country);

    expect(State::query()->where('country_id', $country->id)->count())->toBe(81)
        ->and(State::query()->where('country_id', $country->id)->where('code', '34')->value('name'))->toBe('İstanbul');
});

it('maps the bundled province and district rows', function (): void {
    $rows = turkiyeCsvRows();
    $byType = array_count_values(array_column($rows, 'type'));

    expect($rows)->toHaveCount(1054)
        ->and($byType['province'] ?? 0)->toBe(81)
        ->and($byType['district'] ?? 0)->toBe(973);

    $byId = array_column($rows, null, 'source_id');
    $orphans = [];

    foreach ($rows as $row) {
        if ($row['parent_source_id'] !== '' && ! isset($byId[$row['parent_source_id']])) {
            $orphans[] = $row['source_id'];
        }
    }

    expect($orphans)->toBe([]);

    expect($byId['tr:district:zonguldak:eregli']['name'])->toBe('Ereğli')
        ->and($byId['tr:district:zonguldak:eregli']['parent_source_id'])->toBe('tr:province:zonguldak')
        ->and($byId['tr:district:konya:eregli']['name'])->toBe('Ereğli')
        ->and($byId['tr:district:konya:eregli']['parent_source_id'])->toBe('tr:province:konya')
        ->and($byId['tr:district:sinop:merkez']['name'])->toBe('Merkez')
        ->and($byId['tr:district:sinop:merkez']['parent_source_id'])->toBe('tr:province:sinop')
        ->and($byId['tr:district:adyaman:kahta']['name'])->toBe('Kâhta')
        ->and($byId['tr:district:samsun:19-mays']['name'])->toBe('19 Mayıs');

    $first = app(TurkiyeGeographyProvider::class)->addressAreaSource()->areas()->first();

    expect($first->sourceId)->toBe('tr:province:adana')
        ->and($first->name)->toBe('Adana')
        ->and($first->code)->toBe('01')
        ->and($first->level)->toBe(1)
        ->and($first->parentSourceId)->toBeNull()
        ->and($first->source)->toBe(TurkiyeGeographyProvider::AREA_SOURCE);
});

it('exposes aliases, roles, and relationships', function (): void {
    $country = $this->seedCountry('TR');
    $provider = app(TurkiyeGeographyProvider::class);

    $names = $provider->areaNames($country);

    expect($names)->toHaveCount(2)
        ->and($names['tr:district:ankara:kahramankazan'] ?? [])->toBe([['name' => 'Kazan', 'name_type' => 'historic']])
        ->and($names['tr:district:zonguldak:eregli'] ?? [])->toBe([['name' => 'Karadeniz Ereğli', 'name_type' => 'common']]);

    $roles = $provider->areaRoles($country);

    expect($roles)->toHaveCount(1054)
        ->and($roles['tr:district:zonguldak:eregli'] ?? [])->toBe([['role' => 'district', 'country_code' => 'TR', 'is_primary' => true]])
        ->and($roles['tr:district:sinop:merkez'][0]['role'] ?? null)->toBe('district');

    $relationships = $provider->areaRelationships($country);

    expect($relationships['tr:district:zonguldak:eregli'] ?? [])->toBe([[
        'parent_source_id' => 'tr:province:zonguldak',
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
    ]])->and($relationships['tr:district:sinop:merkez'][0]['parent_source_id'] ?? null)->toBe('tr:province:sinop');
});

it('formats Turkish addresses with the postcode left of locality and province', function (): void {
    $formatted = app(TurkiyeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Doğanbey Mah.',
        'city' => 'ULUS',
        'state' => 'ANKARA',
        'postcode' => '06101',
        'country_code' => 'TR',
    ]));

    expect($formatted)->toBe("Doğanbey Mah.\n06101 ULUS/ANKARA\nTürkiye");
});
