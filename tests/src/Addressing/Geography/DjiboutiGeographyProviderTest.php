<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Djibouti\DjiboutiGeographyProvider;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 20 sub-prefectures under regions with parent links', function (): void {
    $areas = app(DjiboutiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(20)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'dj:region:ali-sabieh'))->toHaveCount(3)
        ->and($l2->where('parentSourceId', 'dj:region:arta'))->toHaveCount(1)
        ->and($l2->where('parentSourceId', 'dj:region:dikhil'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'dj:city:djibouti'))->toHaveCount(1)
        ->and($l2->where('parentSourceId', 'dj:region:obock'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'dj:region:tadjourah'))->toHaveCount(7)
        ->and($byId->get('dj:subprefecture:holhol')->parentSourceId)->toBe('dj:region:ali-sabieh')
        // Lac Assal moved Arta -> Tadjourah in M1 verification (2024 census
        // placement, UPU 77601 routing, GeoNames admin1 DJ-05, Sagallo).
        ->and($byId->get('dj:subprefecture:lac-assal')->parentSourceId)->toBe('dj:region:tadjourah')
        ->and($byId->get('dj:subprefecture:adailou')->parentSourceId)->toBe('dj:region:tadjourah');
});

it('uses the verified town-article spellings', function (): void {
    $byId = app(DjiboutiGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // Flat lists print Adaylou/Adayllou; the town article is Adailou.
    // Lac Assal follows the French local name (district map + UPU profile).
    expect($byId->get('dj:subprefecture:adailou')->name)->toBe('Adailou')
        ->and($byId->get('dj:subprefecture:lac-assal')->name)->toBe('Lac Assal')
        ->and($byId->get('dj:subprefecture:tadjoura')->name)->toBe('Tadjoura')
        ->and($byId->get('dj:subprefecture:khor-angar')->name)->toBe('Khor Angar');
});

it('seeds the 6 ISO 3166-2:DJ first-level divisions', function (): void {
    $country = $this->seedCountry('DJ');

    app(DjiboutiGeographyProvider::class)->seed($country);

    $states = State::query()->where('country_id', $country->id)->orderBy('code')->pluck('name', 'code')->all();

    expect($states)->toHaveCount(6)
        ->and($states['AS'])->toBe('Ali Sabieh')
        ->and($states['AR'])->toBe('Arta')
        ->and($states['DI'])->toBe('Dikhil')
        ->and($states['DJ'])->toBe('Djibouti')
        ->and($states['OB'])->toBe('Obock')
        ->and($states['TA'])->toBe('Tadjourah');
});

it('links the UPU 10-code table to the capital sub-prefectures', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('DJ', $dir . '/djibouti-postal-codes.csv', $dir . '/djibouti-postal-code-areas.csv', 'aiarmada.addressing.djibouti');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(10)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^77[1-6]\d{2}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // UPU DJI profile 05/2020: 77101 Djibouti Ville + 77102-77105 city
    // districts (Marabout/Einguela/Nasser/Balbala) + 5 region-capital villes.
    // Rural localities route via their capital's code (thin by design).
    expect((string) $byCode->get('77101')->areaSourceId)->toBe('dj:subprefecture:djibouti')
        ->and((string) $byCode->get('77102')->areaSourceId)->toBe('dj:subprefecture:djibouti')
        ->and((string) $byCode->get('77103')->areaSourceId)->toBe('dj:subprefecture:djibouti')
        ->and((string) $byCode->get('77104')->areaSourceId)->toBe('dj:subprefecture:djibouti')
        ->and((string) $byCode->get('77105')->areaSourceId)->toBe('dj:subprefecture:djibouti')
        ->and((string) $byCode->get('77201')->areaSourceId)->toBe('dj:subprefecture:arta')
        ->and((string) $byCode->get('77301')->areaSourceId)->toBe('dj:subprefecture:ali-sabieh')
        ->and((string) $byCode->get('77401')->areaSourceId)->toBe('dj:subprefecture:dikhil')
        ->and((string) $byCode->get('77501')->areaSourceId)->toBe('dj:subprefecture:obock')
        ->and((string) $byCode->get('77601')->areaSourceId)->toBe('dj:subprefecture:tadjoura');
});
