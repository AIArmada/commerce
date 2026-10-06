<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\CostaRica\CostaRicaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 7 provinces with 84 cantons and parent links', function (): void {
    $areas = app(CostaRicaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($areas->where('level', 1))->toHaveCount(7)
        ->and($l2)->toHaveCount(84)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();
});

it('pins the verified CR tree of 20/16/8/10/11/13/6 cantons', function (): void {
    $areas = app(CostaRicaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('parentSourceId', 'cr:province:san-jose'))->toHaveCount(20)
        ->and($areas->where('parentSourceId', 'cr:province:alajuela'))->toHaveCount(16)
        ->and($areas->where('parentSourceId', 'cr:province:cartago'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'cr:province:heredia'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'cr:province:guanacaste'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'cr:province:puntarenas'))->toHaveCount(13)
        ->and($areas->where('parentSourceId', 'cr:province:limon'))->toHaveCount(6);

    // Post-2017 cantons with their parents.
    expect($byId->get('cr:canton:rio-cuarto')->parentSourceId)->toBe('cr:province:alajuela')
        ->and($byId->get('cr:canton:sarchi')->parentSourceId)->toBe('cr:province:alajuela')
        ->and($byId->get('cr:canton:monteverde')->parentSourceId)->toBe('cr:province:puntarenas')
        ->and($byId->get('cr:canton:puerto-jimenez')->parentSourceId)->toBe('cr:province:puntarenas');
});

it('bundles the B11-verified 492-code DIVTER overlay with zero duals', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('CR', $dir . '/costa-rica-postal-codes.csv', $dir . '/costa-rica-postal-code-areas.csv', 'aiarmada.addressing.costa-rica');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(492)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(492)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(492)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^[1-7]\\d{4}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // New-canton codes + Lagunillas (Garabito 3rd district, law Nov 2020).
    expect($primary('21601'))->toBe('cr:canton:rio-cuarto')
        ->and($primary('21603'))->toBe('cr:canton:rio-cuarto')
        ->and($primary('61201'))->toBe('cr:canton:monteverde')
        ->and($primary('61301'))->toBe('cr:canton:puerto-jimenez')
        ->and($primary('61103'))->toBe('cr:canton:garabito');

    // Superseded pre-split codes correctly absent.
    expect($byCode->has('20306'))->toBeFalse()
        ->and($byCode->has('60109'))->toBeFalse()
        ->and($byCode->has('60702'))->toBeFalse();

    // Zero duals: every code links exactly once, all primary.
    $multi = $byCode->filter(static fn ($rows): bool => $rows->count() > 1)->keys()->all();
    expect($multi)->toBe([]);

    // Per-canton primary counts pin the district table.
    $counts = $postcodes->where('isPrimary', true)->countBy('areaSourceId');
    expect($counts->get('cr:canton:san-jose'))->toBe(11)
        ->and($counts->get('cr:canton:desamparados'))->toBe(13)
        ->and($counts->get('cr:canton:alajuela'))->toBe(14)
        ->and($counts->get('cr:canton:san-carlos'))->toBe(13)
        ->and($counts->get('cr:canton:rio-cuarto'))->toBe(3)
        ->and($counts->get('cr:canton:puntarenas'))->toBe(15)
        ->and($counts->get('cr:canton:golfito'))->toBe(3)
        ->and($counts->get('cr:canton:garabito'))->toBe(3)
        ->and($counts->get('cr:canton:parrita'))->toBe(1);
});
