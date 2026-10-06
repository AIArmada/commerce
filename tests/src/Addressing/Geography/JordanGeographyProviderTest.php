<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Jordan\JordanGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Jordan tree of 12 governorates and 51 liwa', function (): void {
    $areas = app(JordanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'governorate'))->toHaveCount(12)
        ->and($areas->where('type', 'liwa'))->toHaveCount(51)
        ->and($areas->where('parentSourceId', 'jo:governorate:amman'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'jo:governorate:irbid'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'jo:governorate:karak'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'jo:governorate:jerash'))->toHaveCount(1);

    // ISO 3166-2:JO codes; liwa mapping per the DoS-census adjudication.
    expect($byId->get('jo:governorate:amman')->code)->toBe('AM')
        ->and($byId->get('jo:governorate:ma-an')->code)->toBe('MN')
        ->and($byId->get('jo:governorate:ma-an')->name)->toBe("Ma'an")
        ->and($byId->get('jo:governorate:zarqa')->code)->toBe('AZ')
        ->and($byId->get('jo:liwa:ajloun:ajloun-qasabah')->name)->toBe('Ajloun Qasabah')
        ->and($byId->get('jo:liwa:ajloun:kufranjah')->parentSourceId)->toBe('jo:governorate:ajloun');
});

it('bundles the verified 351-code overlay with exact primaries and one dual', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('JO', $dir . '/jordan-postal-codes.csv', $dir . '/jordan-postal-code-areas.csv', 'aiarmada.addressing.jordan');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(352)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(351)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(351)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{5}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Border adjudication anchors from the DoS-census / ar.wiki pass.
    expect($primary('71910'))->toBe('jo:liwa:ma-an:shobak')
        ->and($primary('61258'))->toBe('jo:liwa:amman:sahab')
        ->and($primary('64710'))->toBe('jo:liwa:tafilah:hasa')
        ->and($primary('25710'))->toBe('jo:liwa:mafraq:mafraq-qasabah')
        ->and($primary('11190'))->toBe('jo:liwa:amman:amman-qasabah')
        ->and($primary('11134'))->toBe('jo:liwa:amman:marka')
        ->and($primary('11152'))->toBe('jo:liwa:amman:quaismeh')
        ->and($primary('71221'))->toBe('jo:liwa:ajloun:ajloun-qasabah')
        ->and($primary('71228'))->toBe('jo:liwa:jerash:jerash-qasabah')
        ->and($primary('61256'))->toBe('jo:liwa:amman:quaismeh');

    // The single dual link: 11121 Wadi Essier primary + Jami'ah secondary.
    $legs = $byCode->get('11121')
        ->mapWithKeys(static fn ($row): array => [(string) $row->areaSourceId => $row->isPrimary])
        ->all();
    expect($legs)->toBe(['jo:liwa:amman:wadi-essier' => true, 'jo:liwa:amman:al-jami-ah' => false]);

    // Three liwa stay codeless in every source.
    expect($postcodes->where('areaSourceId', 'jo:liwa:ajloun:kufranjah'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'jo:liwa:tafilah:bsaira'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'jo:liwa:balqa:shoonah-janoobiyah'))->toHaveCount(0);
});
