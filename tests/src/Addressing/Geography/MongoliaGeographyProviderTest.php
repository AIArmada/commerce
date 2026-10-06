<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Mongolia\MongoliaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 22 L1 areas with 339 sums/duuregs under L1 parents', function (): void {
    $areas = app(MongoliaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(361)
        ->and($l1)->toHaveCount(22)
        ->and($l2)->toHaveCount(339)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    expect($byId->get('mn:province:bulgan')->name)->toBe('Bulgan')
        ->and($byId->get('mn:province:khentii')->name)->toBe('Khentii')
        ->and($byId->get('mn:province:khovd')->name)->toBe('Khovd');
});

it('pins the B18 display-label renames with old slugs retired', function (): void {
    $areas = app(MongoliaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($byId->get('mn:sum:batshireet')->name)->toBe('Batshireet')
        ->and($byId->get('mn:sum:batshireet')->parentSourceId)->toBe('mn:province:khentii')
        ->and($byId->has('mn:sum:eg'))->toBeFalse()
        ->and($byId->get('mn:sum:bulgan:bayannuur')->name)->toBe('Bayannuur')
        ->and($byId->has('mn:sum:bayanuur'))->toBeFalse()
        ->and($byId->get('mn:sum:khovd:jargalant')->name)->toBe('Jargalant')
        ->and($byId->get('mn:sum:khovd:khovd')->name)->toBe('Khovd')
        ->and($byId->has('mn:sum:jargalant-khovd-city'))->toBeFalse();
});

it('pins the 39 Ulaanbaatar postcode links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MN', $dir . '/mongolia-postal-codes.csv', $dir . '/mongolia-postal-code-areas.csv', 'aiarmada.addressing.mongolia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(39);

    $byCode = $postcodes->groupBy->code;

    expect($byCode->get('13260')->first()->areaSourceId)->toBe('mn:duureg:ulaanbaatar:bayanzurkh');
});
