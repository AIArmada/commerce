<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Brunei\BruneiGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Brunei tree of 4 districts and 39 mukims', function (): void {
    $areas = app(BruneiGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'district'))->toHaveCount(4)
        ->and($areas->where('type', 'mukim'))->toHaveCount(39)
        ->and($areas->where('parentSourceId', 'bn:district:brunei-muara'))->toHaveCount(18)
        ->and($areas->where('parentSourceId', 'bn:district:belait'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'bn:district:tutong'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'bn:district:temburong'))->toHaveCount(5);
});

it('bundles one primary kampung postcode per link with valid Brunei formats', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BN', $dir . '/brunei-postal-codes.csv', $dir . '/brunei-postal-code-areas.csv', 'aiarmada.addressing.brunei');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(394)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^[A-Z]{2}\d{4}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // Genuine prefix splits from Brunei Post data: BE across Gadong A/B,
    // BK across Sungai Kebun/Kedayan.
    expect((string) $byCode->get('BE1318')->areaSourceId)->toBe('bn:mukim:gadong-b')
        ->and((string) $byCode->get('BE2119')->areaSourceId)->toBe('bn:mukim:gadong-a')
        ->and((string) $byCode->get('BK1711')->areaSourceId)->toBe('bn:mukim:sungai-kedayan')
        ->and((string) $byCode->get('BK1725')->areaSourceId)->toBe('bn:mukim:sungai-kebun');

    // Agency and locked-bag codes are deliberately excluded.
    expect($byCode->has('BS8670'))->toBeFalse()
        ->and($byCode->has('BA1710'))->toBeFalse()
        ->and($byCode->has('KB3534'))->toBeFalse();
});

it('exposes the Brunei Post finder spellings as alternative names', function (): void {
    $names = app(BruneiGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['bn:mukim:burong-pingai-ayer'])->toContain(
        ['name' => 'Burong Pinggai Ayer', 'name_type' => 'alternative'],
    )->and($names['bn:mukim:peramu'])->toContain(
        ['name' => 'Kampong Peramu', 'name_type' => 'alternative'],
    );
});
