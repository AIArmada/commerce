<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Azerbaijan\AzerbaijanGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('assigns LA to Lankaran city and LAN to Lankaran district', function (): void {
    $areas = app(AzerbaijanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('az:municipality:lankaran')->code)->toBe('LA')
        ->and($areas->get('az:district:lankaran')->code)->toBe('LAN')
        ->and($areas->get('az:district:khojavend')->code)->toBe('XVD');

    $names = app(AzerbaijanGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['az:district:khojavend'][0]['name'])->toBe('Martuni');
});

it('exposes corrected Azerbaijani district slugs and names', function (): void {
    $areas = app(AzerbaijanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('az:district:fuzuli')->name)->toBe('Fuzuli')
        ->and($areas->get('az:district:fuzuli')->code)->toBe('FUZ')
        ->and($areas->get('az:district:ismayilli')->name)->toBe('Ismayilli')
        ->and($areas->get('az:district:ismayilli')->code)->toBe('ISM')
        ->and($areas->get('az:district:khojaly')->name)->toBe('Khojaly')
        ->and($areas->get('az:district:khojaly')->code)->toBe('XCI')
        ->and($areas->get('az:district:gdby')->name)->toBe('Gadabay')
        ->and($areas->get('az:district:gdby')->type)->toBe('district')
        ->and($areas->get('az:district:agdam')->name)->toBe('Agdam')
        ->and($areas->has('az:district:fizuli'))->toBeFalse()
        ->and($areas->has('az:district:ismailli'))->toBeFalse()
        ->and($areas->has('az:district:khojali'))->toBeFalse();
});

it('ships 685 local municipalities under districts and cities', function (): void {
    $areas = app(AzerbaijanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(685)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('az:local_municipality:kis')->parentSourceId)->toBe('az:district:shaki')
        ->and($byId->get('az:local_municipality:asagi-quscu')->parentSourceId)->toBe('az:district:tovuz')
        ->and($byId->get('az:local_municipality:seki')->parentSourceId)->toBe('az:municipality:shaki');
});

it('roles first-level cities with the district selector', function (): void {
    $roles = app(AzerbaijanGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['az:municipality:baku'][0]['role'])->toBe('district')
        ->and($roles['az:autonomous_republic:nakhchivan'][0]['role'])->toBe('autonomous_republic');
});

it('pins the verified Azerbaijan tree of 78 first-level areas', function (): void {
    $areas = app(AzerbaijanGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'district'))->toHaveCount(66)
        ->and($areas->where('type', 'municipality'))->toHaveCount(11)
        ->and($areas->where('type', 'autonomous_republic'))->toHaveCount(1)
        ->and($areas->where('level', 1))->toHaveCount(78)
        ->and($areas->where('parentSourceId', 'az:municipality:baku'))->toHaveCount(45)
        ->and($areas->where('parentSourceId', 'az:district:tovuz'))->toHaveCount(20)
        ->and($areas->where('parentSourceId', 'az:municipality:shaki'))->toHaveCount(1);

    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:AZ codes, including the non-obvious ones (CAB/CAL/CUL/QOB/
    // GYG/UCA/XAC/XIZ/XCI/XVD); NX is the autonomous republic.
    expect($byId->get('az:autonomous_republic:nakhchivan')->code)->toBe('NX')
        ->and($byId->get('az:district:jabrayil')->code)->toBe('CAB')
        ->and($byId->get('az:district:jalilabad')->code)->toBe('CAL')
        ->and($byId->get('az:district:julfa')->code)->toBe('CUL')
        ->and($byId->get('az:district:gobustan')->code)->toBe('QOB')
        ->and($byId->get('az:district:goygol')->code)->toBe('GYG')
        ->and($byId->get('az:district:ujar')->code)->toBe('UCA')
        ->and($byId->get('az:district:khachmaz')->code)->toBe('XAC')
        ->and($byId->get('az:municipality:nakhchivan')->code)->toBe('NV')
        ->and($byId->get('az:municipality:khankendi')->code)->toBe('XA');
});

it('bundles the 1186 district postcodes as single primary L1 links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('AZ', $dir . '/azerbaijan-postal-codes.csv', $dir . '/azerbaijan-postal-code-areas.csv', 'aiarmada.addressing.azerbaijan');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(1186)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^AZ \\d{4}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue()
        ->and($postcodes->pluck('code')->unique())->toHaveCount(1186);

    $byCode = $postcodes->keyBy->code;

    // UPU AZE profile anchors: AZ1010/AZ1014/AZ1000 Baku.
    expect((string) $byCode->get('AZ 1010')->areaSourceId)->toBe('az:municipality:baku')
        ->and((string) $byCode->get('AZ 1014')->areaSourceId)->toBe('az:municipality:baku')
        ->and((string) $byCode->get('AZ 1000')->areaSourceId)->toBe('az:municipality:baku');

    // City/district splits: NN00+eponym+N Sayli branches hold the city.
    expect((string) $byCode->get('AZ 5500')->areaSourceId)->toBe('az:municipality:shaki')
        ->and((string) $byCode->get('AZ 5511')->areaSourceId)->toBe('az:district:shaki')
        ->and((string) $byCode->get('AZ 4200')->areaSourceId)->toBe('az:municipality:lankaran')
        ->and((string) $byCode->get('AZ 4212')->areaSourceId)->toBe('az:district:lankaran')
        ->and((string) $byCode->get('AZ 6600')->areaSourceId)->toBe('az:municipality:yevlakh')
        ->and((string) $byCode->get('AZ 6611')->areaSourceId)->toBe('az:district:yevlakh');

    // Nominatim-reverse pins (GN coords).
    expect((string) $byCode->get('AZ 8000')->areaSourceId)->toBe('az:district:khizi')
        ->and((string) $byCode->get('AZ 2600')->areaSourceId)->toBe('az:municipality:khankendi')
        ->and((string) $byCode->get('AZ 3900')->areaSourceId)->toBe('az:district:qubadli')
        ->and((string) $byCode->get('AZ 4100')->areaSourceId)->toBe('az:district:lachin');

    // Per-area primary counts (68 of 78 L1 covered; Nakhchivan + Jabrayil gap).
    $counts = $postcodes->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts)->toHaveCount(68)
        ->and($counts->get('az:municipality:baku'))->toBe(142)
        ->and($counts->get('az:district:masally'))->toBe(40)
        ->and($counts->get('az:district:zaqatala'))->toBe(30)
        ->and($counts->get('az:district:khizi'))->toBe(2)
        ->and($counts->get('az:district:qubadli'))->toBe(1);
});
