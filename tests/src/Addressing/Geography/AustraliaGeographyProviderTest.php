<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Australia\AustraliaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 8 states/territories with 539 LGAs under L1 parents', function (): void {
    $areas = app(AustraliaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(547)
        ->and($l1)->toHaveCount(8)
        ->and($l2)->toHaveCount(539)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    expect($byId->get('au:state:new-south-wales')->code)->toBe('NSW')
        ->and($byId->get('au:state:queensland')->code)->toBe('QLD')
        ->and($byId->get('au:territory:northern-territory')->code)->toBe('NT');
});

it('pins the B19 SA tree cells: SLCC + Lower Eyre renames, APY + Maralinga adds', function (): void {
    $areas = app(AustraliaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($byId->get('au:council:district-council-of-grant')->name)->toBe('Southern Limestone Coast Council')
        ->and($byId->get('au:council:district-council-of-lower-eyre-peninsula')->name)->toBe('Lower Eyre Council')
        ->and($byId->get('au:council:anangu-pitjantjatjara-yankunytjatjara')->parentSourceId)->toBe('au:state:south-australia')
        ->and($byId->get('au:council:maralinga-tjarutja')->parentSourceId)->toBe('au:state:south-australia')
        ->and($byId->get('au:council:roxby-council')->name)->toBe('Roxby Council');
});

it('pins the B19 ASGS link pass: 3165 codes with flips, adds, and drops', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('AU', $dir . '/australia-postal-codes.csv', $dir . '/australia-postal-code-areas.csv', 'aiarmada.addressing.australia');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(3165)
        ->and($postcodes)->toHaveCount(4186);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('5150'))->toBe('au:city:city-of-mitcham')
        ->and($primary('5273'))->toBe('au:council:naracoorte-lucindale-council')
        ->and($primary('7469'))->toBe('au:council:west-coast-council')
        ->and($primary('0862'))->toBe('au:region:barkly-region')
        ->and($primary('0885'))->toBe('au:region:groote-archipelago-region')
        ->and($primary('2335'))->toBe('au:council:singleton-council')
        ->and($primary('7215'))->toBe('au:council:break-o-day-council');

    expect($byCode->get('0872')->pluck('areaSourceId')->contains('au:council:anangu-pitjantjatjara-yankunytjatjara'))->toBeTrue()
        ->and($byCode->get('5690')->pluck('areaSourceId')->contains('au:council:maralinga-tjarutja'))->toBeTrue()
        ->and($byCode->get('4605')->pluck('areaSourceId')->contains('au:shire:aboriginal-shire-of-cherbourg'))->toBeTrue()
        ->and($byCode->get('4605')->pluck('areaSourceId')->contains('au:region:gympie-region'))->toBeFalse()
        ->and($byCode->get('0822')->pluck('areaSourceId')->contains('au:city:city-of-darwin'))->toBeFalse()
        ->and($byCode->get('7030')->pluck('areaSourceId')->contains('au:council:derwent-valley-council'))->toBeFalse();
});
