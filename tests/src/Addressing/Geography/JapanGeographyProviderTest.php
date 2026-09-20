<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Japan\JapanAddressFormatter;
use AIArmada\Addressing\Geography\Japan\JapanGeographyProvider;
use AIArmada\Addressing\Models\State;

function japanCsvRows(): array
{
    $providerFile = (string) (new ReflectionClass(JapanGeographyProvider::class))->getFileName();
    $lines = file(dirname($providerFile, 4).'/resources/geography/japan-address-areas.csv');
    $header = str_getcsv((string) array_shift($lines));

    return array_map(static fn (string $line): array => array_combine($header, str_getcsv($line)), $lines);
}

it('defines a two-level administrative hierarchy down to municipalities', function (): void {
    $hierarchies = app(JapanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('prefecture')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[1]->kind)->toBe('area')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('prefecture')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('municipality');
});

it('seeds the 47 prefectures', function (): void {
    $country = $this->seedCountry('JP');

    app(JapanGeographyProvider::class)->seed($country);

    expect(State::query()->where('country_id', $country->id)->count())->toBe(47)
        ->and(State::query()->where('country_id', $country->id)->where('code', '13')->value('name'))->toBe('Tokyo');
});

it('maps the bundled prefecture and municipality rows', function (): void {
    $rows = japanCsvRows();
    $byType = array_count_values(array_column($rows, 'type'));

    expect($rows)->toHaveCount(1794)
        ->and($byType['prefecture'] ?? 0)->toBe(47)
        ->and($byType['municipality'] ?? 0)->toBe(1747);

    $byId = array_column($rows, null, 'source_id');
    $orphans = [];

    foreach ($rows as $row) {
        if ($row['parent_source_id'] !== '' && ! isset($byId[$row['parent_source_id']])) {
            $orphans[] = $row['source_id'];
        }
    }

    expect($orphans)->toBe([]);

    expect($byId['jp:municipality:01100']['name'])->toBe('Sapporo')
        ->and($byId['jp:municipality:01100']['native_name'])->toBe('札幌市')
        ->and($byId['jp:municipality:01100']['parent_source_id'])->toBe('jp:prefecture:hokkaido')
        ->and($byId['jp:municipality:13101']['name'])->toBe('Chiyoda')
        ->and($byId['jp:municipality:13101']['native_name'])->toBe('千代田区')
        ->and($byId['jp:municipality:13101']['parent_source_id'])->toBe('jp:prefecture:tokyo')
        ->and($byId['jp:municipality:01695']['name'])->toBe('Shikotan')
        ->and($byId['jp:municipality:01695']['native_name'])->toBe('色丹村')
        ->and($byId['jp:municipality:01695']['parent_source_id'])->toBe('jp:prefecture:hokkaido')
        ->and($byId['jp:municipality:01403']['name'])->toBe('Tomari')
        ->and($byId['jp:municipality:01696']['name'])->toBe('Tomari')
        ->and($byId['jp:municipality:13206']['parent_source_id'])->toBe('jp:prefecture:tokyo')
        ->and($byId['jp:municipality:34208']['parent_source_id'])->toBe('jp:prefecture:hiroshima');

    $first = app(JapanGeographyProvider::class)->addressAreaSource()->areas()->first();

    expect($first->sourceId)->toBe('jp:prefecture:hokkaido')
        ->and($first->name)->toBe('Hokkaido')
        ->and($first->code)->toBe('01')
        ->and($first->level)->toBe(1)
        ->and($first->parentSourceId)->toBeNull()
        ->and($first->source)->toBe(JapanGeographyProvider::AREA_SOURCE);
});

it('exposes roles and relationships for prefectures and municipalities', function (): void {
    $country = $this->seedCountry('JP');
    $provider = app(JapanGeographyProvider::class);

    expect($provider->areaNames($country))->toBe([]);

    $roles = $provider->areaRoles($country);

    expect($roles)->toHaveCount(1794)
        ->and($roles['jp:prefecture:tokyo'] ?? [])->toBe([['role' => 'prefecture', 'country_code' => 'JP', 'is_primary' => true]])
        ->and($roles['jp:municipality:13101'] ?? [])->toBe([['role' => 'municipality', 'country_code' => 'JP', 'is_primary' => true]])
        ->and($roles['jp:municipality:01695'][0]['role'] ?? null)->toBe('municipality');

    $relationships = $provider->areaRelationships($country);

    expect($relationships['jp:municipality:01100'] ?? [])->toBe([[
        'parent_source_id' => 'jp:prefecture:hokkaido',
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
    ]])->and($relationships['jp:municipality:01696'][0]['parent_source_id'] ?? null)->toBe('jp:prefecture:hokkaido');
});

it('formats Japanese addresses with city, prefecture and postcode below', function (): void {
    $formatted = app(JapanAddressFormatter::class)->format(AddressData::from([
        'line1' => '10-23, Mitsugi 1-chome',
        'city' => 'Musashi-Murayama-shi',
        'state' => 'TOKYO',
        'postcode' => '231-0012',
        'country_code' => 'JP',
    ]));

    expect($formatted)->toBe("10-23, Mitsugi 1-chome\nMusashi-Murayama-shi, TOKYO\n231-0012\nJapan");
});
