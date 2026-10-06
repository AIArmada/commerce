<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Turkmenistan\TurkmenistanGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Turkmenistan tree of 5 regions, Ashgabat, and 58 districts', function (): void {
    $areas = app(TurkmenistanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(64)
        ->and($areas->where('type', 'region'))->toHaveCount(5)
        ->and($areas->where('type', 'city'))->toHaveCount(1)
        ->and($areas->where('type', 'district'))->toHaveCount(58)
        ->and($areas->where('parentSourceId', 'tm:region:ahal'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'tm:city:ashgabat'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'tm:region:balkan'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'tm:region:dasoguz'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'tm:region:lebap'))->toHaveCount(12)
        ->and($areas->where('parentSourceId', 'tm:region:mary'))->toHaveCount(12);

    // ISO 3166-2:TM codes (S = Ashgabat).
    expect($byId->get('tm:region:ahal')->code)->toBe('A')
        ->and($byId->get('tm:city:ashgabat')->code)->toBe('S')
        ->and($byId->get('tm:region:balkan')->code)->toBe('B')
        ->and($byId->get('tm:region:dasoguz')->code)->toBe('D')
        ->and($byId->get('tm:region:lebap')->code)->toBe('L')
        ->and($byId->get('tm:region:mary')->code)->toBe('M');

    // Cities and boroughs live at district level; names carry no wiki markup.
    expect($byId->get('tm:district:mary')->name)->toBe('Mary')
        ->and($byId->get('tm:district:turkmenbasy')->name)->toBe('Türkmenbaşy')
        ->and($byId->get('tm:district:awaza')->parentSourceId)->toBe('tm:region:balkan')
        ->and($areas->every(fn ($a): bool => ! str_contains($a->name, "'''")))->toBeTrue();
});

it('pins the verified Turkmenistan multi-code map: 49 codes over 73 links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('TM', $dir . '/turkmenistan-postal-codes.csv', $dir . '/turkmenistan-postal-code-areas.csv', 'aiarmada.addressing.turkmenistan');

    $links = $source->postalCodes()->collect();

    expect($links)->toHaveCount(73)
        ->and($links->pluck('code')->unique())->toHaveCount(49)
        ->and($links->every(static fn ($row): bool => (bool) preg_match('/^\\d{6}$/', (string) $row->code)))->toBeTrue();

    $byCode = $links->groupBy->code;

    // Ashgabat general code spans the city boroughs; Berkararlyk holds the primary.
    $ashgabat = $byCode->get('744000');
    expect($ashgabat)->toHaveCount(4)
        ->and($ashgabat->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['tm:district:berkararlyk']);

    // District-pair exemplars: city/etrap twin (746000), cross-district (745420).
    expect($byCode->get('746000')->pluck('areaSourceId')->sort()->values()->all())
        ->toBe(['tm:district:bayramaly', 'tm:district:mary:bayramaly'])
        ->and($byCode->get('745420')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['tm:district:sakarcage']);

    // Per-district primary counts.
    $counts = $links->where('isPrimary', true)->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts->get('tm:district:akdepe'))->toBe(2)
        ->and($counts->get('tm:district:balkanabat'))->toBe(2)
        ->and($counts->get('tm:district:murgap'))->toBe(2)
        ->and($counts->get('tm:district:turkmengala'))->toBe(1)
        ->and($counts->get('tm:district:berkararlyk'))->toBe(1);
});
