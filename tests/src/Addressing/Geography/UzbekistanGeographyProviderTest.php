<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Uzbekistan\UzbekistanAddressFormatter;
use AIArmada\Addressing\Geography\Uzbekistan\UzbekistanGeographyProvider;
use AIArmada\Addressing\Models\State;

function uzbekistanCsvRows(): array
{
    $providerFile = (string) (new ReflectionClass(UzbekistanGeographyProvider::class))->getFileName();
    $lines = file(dirname($providerFile, 4) . '/resources/geography/uzbekistan-address-areas.csv');
    $header = str_getcsv((string) array_shift($lines));

    return array_map(static fn (string $line): array => array_combine($header, str_getcsv($line)), $lines);
}

it('defines a two-level administrative hierarchy down to tumans', function (): void {
    $hierarchies = app(UzbekistanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('tuman')
        ->and($hierarchies[0]->levels[1]->label)->toBe('Tuman')
        ->and($hierarchies[0]->levels[1]->kind)->toBe('area')
        ->and($hierarchies[0]->levels[1]->areaTypes)->toBe(['tuman', 'city'])
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('region')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('tuman');
});

it('seeds the 12 regions plus Karakalpakstan and Tashkent city', function (): void {
    $country = $this->seedCountry('UZ');

    app(UzbekistanGeographyProvider::class)->seed($country);

    expect(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(State::query()->where('country_id', $country->id)->where('code', 'QR')->value('name'))->toBe('Karakalpakstan')
        ->and(State::query()->where('country_id', $country->id)->where('code', 'TK')->value('name'))->toBe('Tashkent City');
});

it('maps the bundled region, tuman, and city rows', function (): void {
    $rows = uzbekistanCsvRows();
    $byType = array_count_values(array_column($rows, 'type'));
    $byLevel = array_count_values(array_column($rows, 'level'));

    expect($rows)->toHaveCount(220)
        ->and($byType['region'] ?? 0)->toBe(12)
        ->and($byType['republic'] ?? 0)->toBe(1)
        ->and($byType['tuman'] ?? 0)->toBe(175)
        ->and($byType['city'] ?? 0)->toBe(32)
        ->and($byLevel['1'] ?? 0)->toBe(14)
        ->and($byLevel['2'] ?? 0)->toBe(206);

    $byId = array_column($rows, null, 'source_id');
    $orphans = [];

    foreach ($rows as $row) {
        if ($row['parent_source_id'] !== '' && ! isset($byId[$row['parent_source_id']])) {
            $orphans[] = $row['source_id'];
        }
    }

    expect($orphans)->toBe([]);

    expect($byId['uz:tuman:qashqadaryo:kokdala']['name'])->toBe("Ko'kdala")
        ->and($byId['uz:tuman:qashqadaryo:kokdala']['parent_source_id'])->toBe('uz:region:qashqadaryo')
        ->and($byId['uz:tuman:bukhara:kogon']['parent_source_id'])->toBe('uz:region:bukhara')
        ->and($byId['uz:city:bukhara:kogon']['type'])->toBe('city')
        ->and($byId['uz:city:bukhara:kogon']['parent_source_id'])->toBe('uz:region:bukhara')
        ->and($byId['uz:city:tashkent-region:nurafshon']['parent_source_id'])->toBe('uz:region:tashkent-region')
        ->and($byId['uz:tuman:tashkent-city:shayxontoxur']['parent_source_id'])->toBe('uz:city:tashkent-city');

    $tashkentCityChildren = array_filter(
        $rows,
        static fn (array $row): bool => $row['parent_source_id'] === 'uz:city:tashkent-city',
    );

    expect($tashkentCityChildren)->toHaveCount(12);

    $first = app(UzbekistanGeographyProvider::class)->addressAreaSource()->areas()->first();

    expect($first->sourceId)->toBe('uz:region:andijan')
        ->and($first->name)->toBe('Andijan')
        ->and($first->code)->toBe('AN')
        ->and($first->level)->toBe(1)
        ->and($first->parentSourceId)->toBeNull()
        ->and($first->source)->toBe(UzbekistanGeographyProvider::AREA_SOURCE);
});

it('exposes aliases, level-aware roles, and relationships', function (): void {
    $country = $this->seedCountry('UZ');
    $provider = app(UzbekistanGeographyProvider::class);

    $names = $provider->areaNames($country);

    expect($names)->toHaveCount(3)
        ->and($names['uz:tuman:tashkent-city:shayxontoxur'] ?? [])->toBe([['name' => 'Shayxontohur', 'name_type' => 'alternative']])
        ->and($names['uz:tuman:tashkent-city:sirgali'] ?? [])->toBe([['name' => 'Sergeli', 'name_type' => 'alternative']])
        ->and($names['uz:tuman:xorazm:hazorasp'] ?? [])->toBe([['name' => 'Xazorasp', 'name_type' => 'alternative']]);

    $roles = $provider->areaRoles($country);

    expect($roles)->toHaveCount(220)
        ->and($roles['uz:tuman:fergana:ozbekiston'] ?? [])->toBe([['role' => 'tuman', 'country_code' => 'UZ', 'is_primary' => true]])
        ->and($roles['uz:city:sirdaryo:yangiyer'][0]['role'] ?? null)->toBe('tuman')
        ->and($roles['uz:city:tashkent-city'][0]['role'] ?? null)->toBe('region')
        ->and($roles['uz:republic:karakalpakstan'][0]['role'] ?? null)->toBe('region');

    $relationships = $provider->areaRelationships($country);

    expect($relationships)->toHaveCount(206)
        ->and($relationships['uz:tuman:andijan:qorgontepa'] ?? [])->toBe([[
            'parent_source_id' => 'uz:region:andijan',
            'relationship_type' => 'contains',
            'hierarchy_type' => 'administrative',
        ]])->and($relationships['uz:city:xorazm:xiva'][0]['parent_source_id'] ?? null)->toBe('uz:region:xorazm');
});

it('formats Uzbek addresses with the postcode and comma left of the locality', function (): void {
    $formatted = app(UzbekistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'pr-t Mustakillik, d. 5, kv. 12',
        'city' => 'g. Tashkent 123',
        'postcode' => '100123',
        'country_code' => 'UZ',
    ]));

    expect($formatted)->toBe("pr-t Mustakillik, d. 5, kv. 12\n100123, g. Tashkent 123\nUzbekistan");
});
