<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Qatar\QatarAddressFormatter;
use AIArmada\Addressing\Geography\Qatar\QatarGeographyProvider;
use AIArmada\Addressing\Models\State;

function qatarCsvRows(): array
{
    $providerFile = (string) (new ReflectionClass(QatarGeographyProvider::class))->getFileName();
    $lines = file(dirname($providerFile, 4) . '/resources/geography/qatar-address-areas.csv');
    $header = str_getcsv((string) array_shift($lines));

    return array_map(static fn (string $line): array => array_combine($header, str_getcsv($line)), $lines);
}

it('defines a two-level administrative hierarchy down to zones', function (): void {
    $hierarchies = app(QatarGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('zone')
        ->and($hierarchies[0]->levels[1]->kind)->toBe('area')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('municipality')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('zone');
});

it('seeds the 8 municipalities', function (): void {
    $country = $this->seedCountry('QA');

    app(QatarGeographyProvider::class)->seed($country);

    expect(State::query()->where('country_id', $country->id)->count())->toBe(8)
        ->and(State::query()->where('country_id', $country->id)->where('code', 'SH')->value('name'))->toBe('Al Sheehaniya');
});

it('maps the bundled municipality and zone rows', function (): void {
    $rows = qatarCsvRows();
    $byType = array_count_values(array_column($rows, 'type'));

    expect($rows)->toHaveCount(98)
        ->and($byType['municipality'] ?? 0)->toBe(8)
        ->and($byType['zone'] ?? 0)->toBe(90);

    $byId = array_column($rows, null, 'source_id');
    $orphans = [];

    foreach ($rows as $row) {
        if ($row['parent_source_id'] !== '' && ! isset($byId[$row['parent_source_id']])) {
            $orphans[] = $row['source_id'];
        }
    }

    expect($orphans)->toBe([]);

    $zoneCodes = array_column(array_filter($rows, static fn (array $row): bool => $row['type'] === 'zone'), 'code');

    expect(array_intersect($zoneCodes, ['8', '9', '10', '11', '59', '87', '88', '89']))->toBe([]);

    expect($byId['qa:zone:doha:57']['name'])->toBe('Zone 57')
        ->and($byId['qa:zone:doha:57']['parent_source_id'])->toBe('qa:municipality:doha')
        ->and($byId['qa:zone:doha:57']['code'])->toBe('57')
        ->and($byId['qa:zone:al-daayen:69']['parent_source_id'])->toBe('qa:municipality:al-daayen')
        ->and($byId['qa:zone:al-rayyan:96']['parent_source_id'])->toBe('qa:municipality:al-rayyan')
        ->and($byId['qa:zone:umm-salal:71']['parent_source_id'])->toBe('qa:municipality:umm-salal')
        ->and($byId['qa:zone:al-wakrah:98']['parent_source_id'])->toBe('qa:municipality:al-wakrah');

    $first = app(QatarGeographyProvider::class)->addressAreaSource()->areas()->first();

    expect($first->sourceId)->toBe('qa:municipality:doha')
        ->and($first->name)->toBe('Doha')
        ->and($first->code)->toBe('DA')
        ->and($first->level)->toBe(1)
        ->and($first->parentSourceId)->toBeNull()
        ->and($first->source)->toBe(QatarGeographyProvider::AREA_SOURCE);
});

it('exposes official district names, roles, and relationships', function (): void {
    $country = $this->seedCountry('QA');
    $provider = app(QatarGeographyProvider::class);

    $names = $provider->areaNames($country);

    expect($names)->toHaveCount(92)
        ->and($names['qa:municipality:doha'] ?? [])->toBe([['name' => 'Ad Dawhah', 'name_type' => 'alternative']])
        ->and($names['qa:zone:al-wakrah:92'] ?? [])->toBe([['name' => 'Mesaieed', 'name_type' => 'official']])
        ->and($names['qa:zone:doha:57'] ?? [])->toBe([['name' => 'Industrial Area', 'name_type' => 'official']])
        ->and($names['qa:zone:doha:50'] ?? [])->toBe([['name' => 'Al Thumama', 'name_type' => 'official']]);

    $roles = $provider->areaRoles($country);

    expect($roles)->toHaveCount(98)
        ->and($roles['qa:municipality:doha'] ?? [])->toBe([['role' => 'municipality', 'country_code' => 'QA', 'is_primary' => true]])
        ->and($roles['qa:zone:doha:57'] ?? [])->toBe([['role' => 'zone', 'country_code' => 'QA', 'is_primary' => true]]);

    $relationships = $provider->areaRelationships($country);

    expect($relationships)->toHaveCount(90)
        ->and($relationships['qa:zone:al-wakrah:92'] ?? [])->toBe([[
            'parent_source_id' => 'qa:municipality:al-wakrah',
            'relationship_type' => 'contains',
            'hierarchy_type' => 'administrative',
        ]])->and($relationships['qa:zone:al-daayen:69'][0]['parent_source_id'] ?? null)->toBe('qa:municipality:al-daayen');
});

it('formats Qatari addresses without a postcode line', function (): void {
    $formatted = app(QatarAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 3263',
        'city' => 'DOHA',
        'country_code' => 'QA',
    ]));

    expect($formatted)->toBe("P.O. Box 3263\nDOHA\nQatar");
});
