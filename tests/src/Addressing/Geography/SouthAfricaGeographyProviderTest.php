<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SouthAfrica\SouthAfricaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified South Africa tree of 9 provinces, 8 metros, 44 districts', function (): void {
    $areas = app(SouthAfricaGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas)->toHaveCount(61)
        ->and($areas->where('type', 'province'))->toHaveCount(9)
        ->and($areas->where('type', 'city_municipality'))->toHaveCount(8)
        ->and($areas->where('type', 'district_municipality'))->toHaveCount(44)
        ->and($areas->where('level', 1))->toHaveCount(9)
        ->and($areas->where('level', 2))->toHaveCount(52);

    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:ZA codes.
    expect($byId->get('za:province:eastern-cape')->code)->toBe('EC')
        ->and($byId->get('za:province:free-state')->code)->toBe('FS')
        ->and($byId->get('za:province:gauteng')->code)->toBe('GP')
        ->and($byId->get('za:province:kwazulu-natal')->code)->toBe('KZN')
        ->and($byId->get('za:province:limpopo')->code)->toBe('LP')
        ->and($byId->get('za:province:mpumalanga')->code)->toBe('MP')
        ->and($byId->get('za:province:north-west')->code)->toBe('NW')
        ->and($byId->get('za:province:northern-cape')->code)->toBe('NC')
        ->and($byId->get('za:province:western-cape')->code)->toBe('WC');

    // Per-province metro+district counts (Alfred Nzo DC44 sits in EC).
    $l2 = $areas->where('level', 2);
    $expected = [
        'za:province:eastern-cape' => 8,
        'za:province:free-state' => 5,
        'za:province:gauteng' => 5,
        'za:province:kwazulu-natal' => 11,
        'za:province:limpopo' => 5,
        'za:province:mpumalanga' => 3,
        'za:province:north-west' => 4,
        'za:province:northern-cape' => 5,
        'za:province:western-cape' => 6,
    ];

    foreach ($expected as $parent => $count) {
        expect($l2->where('parentSourceId', $parent))->toHaveCount($count, $parent);
    }
});

it('bundles the 3277 South Africa codes as 3318 municipal links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('ZA', $dir . '/south-africa-postal-codes.csv', $dir . '/south-africa-postal-code-areas.csv', 'aiarmada.addressing.south_africa');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(3318)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(3277)
        ->and($postcodes->where('isPrimary', false))->toHaveCount(41)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(3277)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{4}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // UPU ZAF profile anchors.
    expect($primary('0083'))->toBe('za:city_municipality:city-of-tshwane') // ARCADIA PRETORIA
        ->and($primary('7975'))->toBe('za:city_municipality:city-of-cape-town') // FISH HOEK
        ->and($primary('1852'))->toBe('za:city_municipality:city-of-johannesburg') // MEADOWLANDS
        ->and($primary('5170'))->toBe('za:district_municipality:or-tambo') // TSOLO
        ->and($primary('0305'))->toBe('za:district_municipality:bojanala') // RUSTENBURG
        ->and($primary('1715'))->toBe('za:city_municipality:city-of-johannesburg'); // FLORIDA

    // B9 fixed cells: postal-town inheritance corrected to street geography.
    expect($primary('9992'))->toBe('za:district_municipality:xhariep') // Bethulie, was Namakwa
        ->and($primary('1861'))->toBe('za:city_municipality:city-of-johannesburg') // Naledi Soweto, was Capricorn
        ->and($primary('0950'))->toBe('za:district_municipality:vhembe') // Thohoyandou, was Capricorn
        ->and($primary('8160'))->toBe('za:district_municipality:west-coast') // Vredendal, was CPT
        ->and($primary('6175'))->toBe('za:city_municipality:nelson-mandela-bay') // Colchester, was Sarah Baartman
        ->and($primary('0924'))->toBe('za:district_municipality:capricorn') // Vivo, was Vhembe
        ->and($primary('1609'))->toBe('za:city_municipality:city-of-ekurhuleni') // Edenvale, was JHB
        ->and($primary('1688'))->toBe('za:city_municipality:city-of-johannesburg') // Rabie Ridge, was EKU
        ->and($primary('1689'))->toBe('za:city_municipality:city-of-ekurhuleni') // Tembisa, was JHB
        ->and($primary('0472'))->toBe('za:district_municipality:nkangala') // Siyabuswa, was Sekhukhune
        ->and($primary('0418'))->toBe('za:district_municipality:bojanala') // Mathibestad, was Tshwane
        ->and($primary('7583'))->toBe('za:city_municipality:city-of-cape-town') // Kuils River, was Sekhukhune
        ->and($primary('4480'))->toBe('za:district_municipality:ilembe') // Darnall, was John Taolo Gaetsewe
        ->and($primary('9358'))->toBe('za:district_municipality:lejweleputswa') // Ikgomotseng, was Mangaung
        ->and($primary('5900'))->toBe('za:district_municipality:chris-hani') // Middelburg EC keep (Inxuba Yethemba)
        ->and($primary('1693'))->toBe('za:city_municipality:city-of-johannesburg'); // Ivory Park keep

    // Metro cluster pins incl stable neighbours.
    expect($primary('0002'))->toBe('za:city_municipality:city-of-tshwane')
        ->and($primary('0003'))->toBe('za:city_municipality:city-of-tshwane')
        ->and($primary('0004'))->toBe('za:city_municipality:city-of-tshwane')
        ->and($primary('1684'))->toBe('za:city_municipality:city-of-johannesburg')
        ->and($primary('1686'))->toBe('za:city_municipality:city-of-johannesburg')
        ->and($primary('1687'))->toBe('za:city_municipality:city-of-johannesburg')
        ->and($primary('7100'))->toBe('za:city_municipality:city-of-cape-town')
        ->and($primary('7101'))->toBe('za:city_municipality:city-of-cape-town')
        ->and($primary('7102'))->toBe('za:city_municipality:city-of-cape-town');

    // B9 fills: street-distinct SAPO+BB codes absent from the old set.
    expect($primary('0180'))->toBe('za:city_municipality:city-of-tshwane')
        ->and($primary('0321'))->toBe('za:district_municipality:bojanala')
        ->and($primary('0323'))->toBe('za:district_municipality:bojanala')
        ->and($primary('0359'))->toBe('za:district_municipality:bojanala')
        ->and($primary('0880'))->toBe('za:district_municipality:capricorn')
        ->and($primary('2310'))->toBe('za:district_municipality:gert-sibande')
        ->and($primary('2539'))->toBe('za:district_municipality:dr-kenneth-kaunda')
        ->and($primary('6445'))->toBe('za:district_municipality:sarah-baartman')
        ->and($primary('6750'))->toBe('za:district_municipality:overberg')
        ->and($primary('9423'))->toBe('za:district_municipality:lejweleputswa');

    // All 41 dual links: 7 kept boundary splits + 33 new + 1682 fill-dual.
    $duals = [
        '0190' => ['za:city_municipality:city-of-tshwane', 'za:district_municipality:bojanala'],
        '0193' => ['za:city_municipality:city-of-tshwane', 'za:district_municipality:bojanala'],
        '0208' => ['za:city_municipality:city-of-tshwane', 'za:district_municipality:bojanala'],
        '0413' => ['za:district_municipality:bojanala', 'za:city_municipality:city-of-tshwane'],
        '0415' => ['za:city_municipality:city-of-tshwane', 'za:district_municipality:bojanala'],
        '0418' => ['za:district_municipality:bojanala', 'za:city_municipality:city-of-tshwane'],
        '0419' => ['za:district_municipality:bojanala', 'za:city_municipality:city-of-tshwane'],
        '0431' => ['za:city_municipality:city-of-tshwane', 'za:district_municipality:nkangala'],
        '0432' => ['za:district_municipality:nkangala', 'za:city_municipality:city-of-tshwane'],
        '0458' => ['za:district_municipality:nkangala', 'za:district_municipality:sekhukhune'],
        '0472' => ['za:district_municipality:nkangala', 'za:district_municipality:sekhukhune'],
        '0477' => ['za:district_municipality:nkangala', 'za:district_municipality:sekhukhune'],
        '0626' => ['za:district_municipality:waterberg', 'za:district_municipality:capricorn'],
        '0721' => ['za:district_municipality:capricorn', 'za:district_municipality:nkangala'],
        '0924' => ['za:district_municipality:capricorn', 'za:district_municipality:vhembe'],
        '0985' => ['za:district_municipality:mopani', 'za:district_municipality:vhembe'],
        '1022' => ['za:city_municipality:city-of-tshwane', 'za:district_municipality:nkangala'],
        '1438' => ['za:city_municipality:city-of-ekurhuleni', 'za:district_municipality:sedibeng'],
        '1609' => ['za:city_municipality:city-of-ekurhuleni', 'za:city_municipality:city-of-johannesburg'],
        '1632' => ['za:city_municipality:city-of-ekurhuleni', 'za:city_municipality:city-of-johannesburg'],
        '1682' => ['za:city_municipality:city-of-johannesburg', 'za:city_municipality:city-of-ekurhuleni'],
        '1685' => ['za:city_municipality:city-of-johannesburg', 'za:city_municipality:city-of-ekurhuleni'],
        '1688' => ['za:city_municipality:city-of-johannesburg', 'za:city_municipality:city-of-ekurhuleni'],
        '1689' => ['za:city_municipality:city-of-ekurhuleni', 'za:city_municipality:city-of-johannesburg'],
        '1693' => ['za:city_municipality:city-of-johannesburg', 'za:city_municipality:city-of-ekurhuleni'],
        '1928' => ['za:district_municipality:sedibeng', 'za:district_municipality:west-rand'],
        '3236' => ['za:district_municipality:umgungundlovu', 'za:district_municipality:umzinyathi'],
        '3276' => ['za:district_municipality:harry-gwala', 'za:district_municipality:umgungundlovu'],
        '3856' => ['za:district_municipality:king-cetshwayo', 'za:district_municipality:zululand'],
        '3950' => ['za:district_municipality:zululand', 'za:district_municipality:umkhanyakude'],
        '3970' => ['za:district_municipality:umkhanyakude', 'za:district_municipality:king-cetshwayo'],
        '4242' => ['za:district_municipality:ugu', 'za:district_municipality:ilembe'],
        '4390' => ['za:district_municipality:ilembe', 'za:city_municipality:ethekwini'],
        '4399' => ['za:city_municipality:ethekwini', 'za:district_municipality:ilembe'],
        '4682' => ['za:district_municipality:ugu', 'za:district_municipality:alfred-nzo'],
        '6139' => ['za:district_municipality:sarah-baartman', 'za:district_municipality:amathole'],
        '7283' => ['za:district_municipality:overberg', 'za:district_municipality:west-coast'],
        '7353' => ['za:city_municipality:city-of-cape-town', 'za:district_municipality:west-coast'],
        '8306' => ['za:district_municipality:frances-baard', 'za:district_municipality:pixley-ka-seme'],
        '8360' => ['za:district_municipality:frances-baard', 'za:district_municipality:pixley-ka-seme'],
        '9850' => ['za:district_municipality:fezile-dabi', 'za:district_municipality:thabo-mofutsanyana'],
    ];

    expect($duals)->toHaveCount(41);

    $secondary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', false)->areaSourceId;

    foreach ($duals as $code => $legs) {
        $code = mb_str_pad((string) $code, 4, '0', STR_PAD_LEFT); // int-cast keys lose leading zeros

        expect($byCode->get($code))->toHaveCount(2, $code)
            ->and($primary($code))->toBe($legs[0], $code)
            ->and($secondary($code))->toBe($legs[1], $code);
    }

    // All 52 municipalities covered; metro + biggest-move spot counts.
    $counts = $postcodes->where('isPrimary', true)->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts)->toHaveCount(52)
        ->and($counts->get('za:city_municipality:city-of-tshwane'))->toBe(210)
        ->and($counts->get('za:city_municipality:city-of-johannesburg'))->toBe(231)
        ->and($counts->get('za:city_municipality:city-of-cape-town'))->toBe(170)
        ->and($counts->get('za:city_municipality:city-of-ekurhuleni'))->toBe(173)
        ->and($counts->get('za:city_municipality:ethekwini'))->toBe(182)
        ->and($counts->get('za:district_municipality:dr-ruth-segomotsi-mompati'))->toBe(56)
        ->and($counts->get('za:district_municipality:frances-baard'))->toBe(31)
        ->and($counts->get('za:district_municipality:chris-hani'))->toBe(62)
        ->and($counts->get('za:district_municipality:nkangala'))->toBe(84);
});
