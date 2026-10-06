<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Bhutan\BhutanGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 20 ISO-coded dzongkhags with the B16 renames', function (): void {
    $areas = app(BhutanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);

    expect($areas)->toHaveCount(225)
        ->and($l1)->toHaveCount(20)
        ->and($l1->map->code->sort()->values()->all())->toBe(['11', '12', '13', '14', '15', '21', '22', '23', '24', '31', '32', '33', '34', '41', '42', '43', '44', '45', 'GA', 'TY'])
        // B16 renames (ISO + WP + district-government domains).
        ->and($byId->get('bt:district:chhukha')->name)->toBe('Chhukha')
        ->and($byId->has('bt:district:chukha'))->toBeFalse()
        ->and($byId->get('bt:district:lhuentse')->name)->toBe('Lhuentse')
        ->and($byId->has('bt:district:lhuntse'))->toBeFalse()
        // Keeps (district governments beat ISO romanization).
        ->and($byId->get('bt:district:mongar')->name)->toBe('Mongar')
        ->and($byId->get('bt:district:pemagatshel')->name)->toBe('Pemagatshel')
        ->and($byId->get('bt:district:trashi-yangtse')->name)->toBe('Trashi Yangtse');
});

it('ships 205 gewogs under their districts', function (): void {
    $areas = app(BhutanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(205)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('parentSourceId', 'bt:district:chhukha'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'bt:district:lhuentse'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'bt:district:mongar'))->toHaveCount(17)
        ->and($byId->get('bt:gewog:phuentsholing')->parentSourceId)->toBe('bt:district:chhukha')
        ->and($byId->get('bt:gewog:gangzur')->parentSourceId)->toBe('bt:district:lhuentse');
});

it('links 38 office base codes at district level', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BT', $dir . '/bhutan-postal-codes.csv', $dir . '/bhutan-postal-code-areas.csv', 'aiarmada.addressing.bhutan');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    expect($byCode)->toHaveCount(38);

    $primary = fn (string $code): string => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // Spots (youbianku office table + OSM; 21104 cross-block confirmed).
    expect($primary('11001'))->toBe('bt:district:thimphu')
        ->and($primary('12002'))->toBe('bt:district:paro')
        ->and($primary('21005'))->toBe('bt:district:chhukha')
        ->and($primary('21101'))->toBe('bt:district:chhukha')
        ->and($primary('21104'))->toBe('bt:district:dagana')
        ->and($primary('31101'))->toBe('bt:district:sarpang')
        ->and($primary('35001'))->toBe('bt:district:dagana')
        ->and($primary('36001'))->toBe('bt:district:tsirang')
        ->and($primary('41104'))->toBe('bt:district:samdrup-jongkhar')
        ->and($primary('45001'))->toBe('bt:district:lhuentse')
        ->and($primary('46002'))->toBe('bt:district:trashi-yangtse');
});
