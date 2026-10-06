<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Albania\AlbaniaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Albania tree of 12 counties and 61 municipalities', function (): void {
    $areas = app(AlbaniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'county'))->toHaveCount(12)
        ->and($areas->where('type', 'municipality'))->toHaveCount(61)
        ->and($areas->where('parentSourceId', 'al:county:berat'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'al:county:diber'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'al:county:durres'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'al:county:elbasan'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'al:county:fier'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'al:county:gjirokaster'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'al:county:korce'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'al:county:kukes'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'al:county:lezhe'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'al:county:shkoder'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'al:county:tirana'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'al:county:vlore'))->toHaveCount(7);

    // ISO 3166-2:AL oracle: Dibër is 09 (capital Peshkopi), Tirana 11, Vlorë 12.
    expect($byId->get('al:county:diber')->code)->toBe('09')
        ->and($byId->get('al:county:tirana')->code)->toBe('11')
        ->and($byId->get('al:county:vlore')->code)->toBe('12')
        ->and($byId->get('al:county:shkoder')->code)->toBe('10');

    // Municipalities-of-Albania oracle: Dimal is the current (2021) name of
    // Ura Vajgurore under Berat; Himarë display spelling; Klos under Dibër.
    expect($byId->get('al:municipality:dimal')->parentSourceId)->toBe('al:county:berat')
        ->and($byId->get('al:municipality:himare')->parentSourceId)->toBe('al:county:vlore')
        ->and($byId->get('al:municipality:klos')->parentSourceId)->toBe('al:county:diber')
        ->and($byId->get('al:municipality:dropull')->parentSourceId)->toBe('al:county:gjirokaster')
        ->and($byId->get('al:municipality:pustec')->parentSourceId)->toBe('al:county:korce');
});

it('bundles the verified 524-code overlay with exact primaries and the 1029 dual', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('AL', $dir . '/albania-postal-codes.csv', $dir . '/albania-postal-code-areas.csv', 'aiarmada.addressing.albania');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(525)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(524)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(524)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{4}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Fills: the four district blocks missing from the GeoNames dump, linked
    // per the official Posta Shqiptare branch list + postzipcode village tables.
    expect($primary('3301'))->toBe('al:municipality:gramsh')
        ->and($primary('3310'))->toBe('al:municipality:gramsh')
        ->and($primary('3501'))->toBe('al:municipality:peqin')
        ->and($primary('3506'))->toBe('al:municipality:peqin')
        ->and($primary('6301'))->toBe('al:municipality:tepelene')
        ->and($primary('6302'))->toBe('al:municipality:memaliaj')
        ->and($primary('6310'))->toBe('al:municipality:memaliaj')
        ->and($primary('6311'))->toBe('al:municipality:tepelene')
        ->and($primary('6401'))->toBe('al:municipality:permet')
        ->and($primary('6402'))->toBe('al:municipality:kelcyre')
        ->and($primary('6409'))->toBe('al:municipality:permet');

    // Retargets: block-capital links moved to the office's own municipality
    // (official office name + Law 115/2014 roster + directory/gazetteer).
    expect($primary('5007'))->toBe('al:municipality:dimal')
        ->and($primary('2013'))->toBe('al:municipality:shijak')
        ->and($primary('3007'))->toBe('al:municipality:cerrik')
        ->and($primary('3008'))->toBe('al:municipality:belsh')
        ->and($primary('9307'))->toBe('al:municipality:patos')
        ->and($primary('9308'))->toBe('al:municipality:mallakaster')
        ->and($primary('9320'))->toBe('al:municipality:roskovec')
        ->and($primary('6003'))->toBe('al:municipality:libohove')
        ->and($primary('6007'))->toBe('al:municipality:dropull')
        ->and($primary('6012'))->toBe('al:municipality:libohove')
        ->and($primary('3403'))->toBe('al:municipality:prrenjas')
        ->and($primary('7020'))->toBe('al:municipality:pustec')
        ->and($primary('7010'))->toBe('al:municipality:devoll')
        ->and($primary('7015'))->toBe('al:municipality:maliq')
        ->and($primary('8002'))->toBe('al:municipality:klos')
        ->and($primary('8014'))->toBe('al:municipality:klos')
        ->and($primary('4030'))->toBe('al:municipality:fushe-arrez')
        ->and($primary('4402'))->toBe('al:municipality:fushe-arrez')
        ->and($primary('4008'))->toBe('al:municipality:vau-i-dejes')
        ->and($primary('4013'))->toBe('al:municipality:vau-i-dejes')
        ->and($primary('9418'))->toBe('al:municipality:himare')
        ->and($primary('9421'))->toBe('al:municipality:selenice')
        ->and($primary('9704'))->toBe('al:municipality:delvine')
        ->and($primary('9705'))->toBe('al:municipality:konispol')
        ->and($primary('9716'))->toBe('al:municipality:finiq')
        ->and($primary('2503'))->toBe('al:municipality:rrogozhine')
        ->and($primary('9022'))->toBe('al:municipality:divjake');

    // Homonym keeps: the Elbasan/Kavaje/Tirana-side twin wins on branch +
    // coords (Mollas, Shushice, Golem, Selite, Sukth, Kolsh, Dajc, Dishnice).
    expect($primary('3019'))->toBe('al:municipality:elbasan')
        ->and($primary('3025'))->toBe('al:municipality:elbasan')
        ->and($primary('2504'))->toBe('al:municipality:kavaje')
        ->and($primary('1046'))->toBe('al:municipality:tirana')
        ->and($primary('1507'))->toBe('al:municipality:kruje')
        ->and($primary('8511'))->toBe('al:municipality:kukes')
        ->and($primary('4506'))->toBe('al:municipality:lezhe')
        ->and($primary('7024'))->toBe('al:municipality:korce')
        ->and($primary('9335'))->toBe('al:municipality:mallakaster')
        ->and($primary('4604'))->toBe('al:municipality:mirdite');

    // 1029 dual: Kamëz primary (2017 official coverage doc + addressed
    // usage at Kthesa e Kamzës), Tirana secondary (GeoNames row retained).
    $legs = $byCode->get('1029')
        ->mapWithKeys(static fn ($row): array => [(string) $row->areaSourceId => $row->isPrimary])
        ->all();
    expect($legs)->toBe(['al:municipality:kamez' => true, 'al:municipality:tirana' => false]);

    // 8707 dropped (GeoNames-only franken-row; Tropojë caps at 8706 in the
    // official branch list, postzipcode, and the Bajram Curri infobox).
    expect($byCode->has('8707'))->toBeFalse();

    // Extras kept: 4511 Shenkoll, 8408 Trebisht, 8409 Fushe-Bulqize
    // (postzipcode village tables); 8520 Morine customs (weak, GN-only).
    expect($primary('4511'))->toBe('al:municipality:lezhe')
        ->and($primary('8408'))->toBe('al:municipality:bulqize')
        ->and($primary('8409'))->toBe('al:municipality:bulqize')
        ->and($primary('8520'))->toBe('al:municipality:kukes');

    // Specials stay on Tirana (Tranzit 1700, EMS 1800 per official lists).
    expect($primary('1700'))->toBe('al:municipality:tirana')
        ->and($primary('1800'))->toBe('al:municipality:tirana');
});
