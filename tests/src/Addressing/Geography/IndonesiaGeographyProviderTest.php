<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Indonesia\IndonesiaGeographyProvider;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\CompositeAddressAreaSource;
use AIArmada\Addressing\Support\CsvAddressAreaSource;

function indonesiaMainCsvRows(): array
{
    $providerFile = (string) (new ReflectionClass(IndonesiaGeographyProvider::class))->getFileName();
    $lines = file(dirname($providerFile, 4) . '/resources/geography/indonesia-address-areas.csv');
    $header = str_getcsv((string) array_shift($lines));

    return array_map(static fn (string $line): array => array_combine($header, str_getcsv($line)), $lines);
}

it('removes island-group stragglers seeded before the bundled data fix', function (): void {
    $country = $this->seedCountry('ID');
    State::query()->create([
        'country_id' => $country->id,
        'code' => 'PP',
        'name' => 'Papua',
        'label' => 'Papua',
    ]);

    app(IndonesiaGeographyProvider::class)->seed($country);

    expect(State::query()->where('country_id', $country->id)->where('code', 'PP')->exists())->toBeFalse()
        ->and(State::query()->where('country_id', $country->id)->where('name', 'Papua')->value('code'))->toBe('PA');
});

it('defines a single administrative hierarchy down to villages', function (): void {
    $hierarchies = app(IndonesiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('regency')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('province')
        ->and($hierarchies[0]->levels[2]->key)->toBe('district')
        ->and($hierarchies[0]->levels[2]->parentKey)->toBe('regency')
        ->and($hierarchies[0]->levels[3]->key)->toBe('village')
        ->and($hierarchies[0]->levels[3]->parentKey)->toBe('district');
});

it('seeds the 38 province states', function (): void {
    $country = $this->seedCountry('ID');

    app(IndonesiaGeographyProvider::class)->seed($country);

    expect(State::query()->where('country_id', $country->id)->count())->toBe(38)
        ->and(State::query()->where('country_id', $country->id)->where('code', 'PE')->value('name'))->toBe('Papua Pegunungan');
});

it('maps the bundled province, regency, and district rows', function (): void {
    $rows = indonesiaMainCsvRows();
    $byType = array_count_values(array_column($rows, 'type'));

    expect($rows)->toHaveCount(7837)
        ->and($byType['province'] ?? 0)->toBe(38)
        ->and($byType['regency'] ?? 0)->toBe(416)
        ->and($byType['city'] ?? 0)->toBe(98)
        ->and($byType['district'] ?? 0)->toBe(7285)
        ->and($byType['village'] ?? 0)->toBe(0)
        ->and($byType['urban_village'] ?? 0)->toBe(0);

    $byId = array_column($rows, null, 'source_id');
    $orphans = [];

    foreach ($rows as $row) {
        if ($row['parent_source_id'] !== '' && ! isset($byId[$row['parent_source_id']])) {
            $orphans[] = $row['source_id'];
        }
    }

    expect($orphans)->toBe([]);

    expect($byId['id:province:32']['name'])->toBe('Jawa Barat')
        ->and($byId['id:province:32']['parent_source_id'])->toBe('')
        ->and($byId['id:regency:3273']['type'])->toBe('city')
        ->and($byId['id:regency:3273']['name'])->toBe('Kota Bandung')
        ->and($byId['id:regency:3273']['parent_source_id'])->toBe('id:province:32')
        ->and($byId['id:district:327302']['name'])->toBe('Coblong')
        ->and($byId['id:district:327302']['parent_source_id'])->toBe('id:regency:3273');

    $first = app(IndonesiaGeographyProvider::class)->addressAreaSource()->areas()->first();

    expect($first->sourceId)->toBe('id:province:11')
        ->and($first->name)->toBe('Aceh')
        ->and($first->code)->toBe('11')
        ->and($first->level)->toBe(1)
        ->and($first->parentSourceId)->toBeNull()
        ->and($first->source)->toBe(IndonesiaGeographyProvider::AREA_SOURCE);
});

it('exposes historic names, roles, relationships, and state mappings', function (): void {
    $country = $this->seedCountry('ID');
    $provider = app(IndonesiaGeographyProvider::class);

    $names = $provider->areaNames($country);
    $mappings = $provider->stateAreaMappings();

    expect($names)->toHaveCount(4)
        ->and($names['id:province:91'] ?? [])->toBe([['name' => 'Irian Jaya', 'name_type' => 'historic']])
        ->and($mappings)->toHaveCount(38)
        ->and($mappings['PE']['area_code'] ?? null)->toBe('95');

    $relationships = $provider->areaRelationships($country);

    expect($relationships['id:regency:3273'] ?? [])->toBe([[
        'parent_source_id' => 'id:province:32',
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
    ]])->and($relationships['id:district:327302'][0]['parent_source_id'] ?? null)->toBe('id:regency:3273');

    $roles = $provider->areaRoles($country);

    expect($roles)->toHaveCount(7837)
        ->and($roles['id:province:32'] ?? [])->toBe([['role' => 'province', 'country_code' => 'ID', 'is_primary' => true]])
        ->and($roles['id:regency:3273'][0]['role'] ?? null)->toBe('regency');
});

it('uses only the main source when the villages flag is off', function (): void {
    expect(app(IndonesiaGeographyProvider::class)->addressAreaSource())->toBeInstanceOf(CsvAddressAreaSource::class);
});

it('streams the opt-in villages when the flag is on', function (): void {
    config()->set('addressing.geography.indonesia.villages', true);

    $source = app(IndonesiaGeographyProvider::class)->addressAreaSource();

    expect($source)->toBeInstanceOf(CompositeAddressAreaSource::class);

    $first = $source->areas()->firstWhere('sourceId', 'id:village:1101012001');

    expect($first->name)->toBe('Keude Bakongan')
        ->and($first->type)->toBe('village')
        ->and($first->code)->toBe('1101012001')
        ->and($first->parentSourceId)->toBe('id:district:110101')
        ->and($first->level)->toBe(4)
        ->and($first->source)->toBe(IndonesiaGeographyProvider::AREA_SOURCE);

    $providerFile = (string) (new ReflectionClass(IndonesiaGeographyProvider::class))->getFileName();
    $lines = file(dirname($providerFile, 4) . '/resources/geography/indonesia-villages.csv');
    $raw = implode('', $lines);

    expect(count($lines) - 1)->toBe(83762)
        ->and(mb_substr_count($raw, ',village,'))->toBe(75266)
        ->and(mb_substr_count($raw, ',urban_village,'))->toBe(8496)
        ->and($raw)->toContain('id:urban_village:1201011001,ID,urban_village,Pasar Batu Gerigis,,1201011001,id:district:120101,4,,');
});
