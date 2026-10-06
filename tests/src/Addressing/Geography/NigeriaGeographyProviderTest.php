<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Nigeria\NigeriaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Nigeria tree of 37 states and 774 LGAs plus area councils', function (): void {
    $areas = app(NigeriaGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'state'))->toHaveCount(37)
        ->and($areas->where('type', 'lga'))->toHaveCount(768)
        ->and($areas->where('type', 'area_council'))->toHaveCount(6)
        ->and($areas->where('level', 1))->toHaveCount(37)
        ->and($areas->where('level', 2))->toHaveCount(774);

    $byId = $areas->keyBy->sourceId;

    // Constitutional per-state distribution spot checks.
    expect($areas->where('parentSourceId', 'ng:state:kano'))->toHaveCount(44)
        ->and($areas->where('parentSourceId', 'ng:state:lagos'))->toHaveCount(20)
        ->and($areas->where('parentSourceId', 'ng:state:bayelsa'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'ng:state:abuja-federal-capital-territory'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'ng:state:ogun'))->toHaveCount(20);

    // Fifth Alteration gazetted names.
    expect($byId->get('ng:lga:ebonyi:afikpo')->name)->toBe('Afikpo')
        ->and($byId->get('ng:lga:ebonyi:edda')->name)->toBe('Edda')
        ->and($byId->get('ng:lga:kano:ghari')->name)->toBe('Ghari')
        ->and($byId->get('ng:lga:ogun:yewa-north')->name)->toBe('Yewa North')
        ->and($byId->get('ng:lga:oyo:atisbo')->name)->toBe('Atisbo')
        ->and($byId->get('ng:lga:rivers:obio-akpor')->name)->toBe('Obio-Akpor');

    // M7 adjudications, all ours-right vs the WP LGA page: Ori Ire is the
    // canonical article (Orire redirects to Modakeke), Okrika/Bursari/Bakura
    // match their state articles, Aiyekire is the constitutional spelling.
    expect($byId->get('ng:lga:oyo:ori-ire')->name)->toBe('Ori Ire')
        ->and($byId->get('ng:lga:oyo:kajola')->parentSourceId)->toBe('ng:state:oyo')
        ->and($byId->get('ng:lga:oyo:surulere')->parentSourceId)->toBe('ng:state:oyo')
        ->and($byId->get('ng:lga:rivers:okrika')->name)->toBe('Okrika')
        ->and($byId->get('ng:lga:yobe:bursari')->name)->toBe('Bursari')
        ->and($byId->get('ng:lga:zamfara:bakura')->name)->toBe('Bakura')
        ->and($byId->get('ng:lga:sokoto:kebbe')->parentSourceId)->toBe('ng:state:sokoto')
        ->and($byId->get('ng:lga:sokoto:shagari')->parentSourceId)->toBe('ng:state:sokoto')
        ->and($byId->get('ng:lga:sokoto:yabo')->parentSourceId)->toBe('ng:state:sokoto')
        ->and($byId->get('ng:lga:ekiti:aiyekire')->name)->toBe('Aiyekire')
        ->and($byId->get('ng:lga:ondo:ile-oluji-okeigbo')->name)->toBe('Ile-Oluji/Okeigbo');
});

it('bundles the 1926 state postcodes as single primary state links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('NG', $dir . '/nigeria-postal-codes.csv', $dir . '/nigeria-postal-code-areas.csv', 'aiarmada.addressing.nigeria');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(1926)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{6}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue()
        ->and($postcodes->pluck('code')->unique())->toHaveCount(1926);

    $byCode = $postcodes->keyBy->code;

    // UPU NGA profile anchors: Mokola 200212 (Oyo), Karshi 900103 (FCT).
    expect((string) $byCode->get('200212')->areaSourceId)->toBe('ng:state:oyo')
        ->and((string) $byCode->get('900103')->areaSourceId)->toBe('ng:state:abuja-federal-capital-territory');

    // Off-zone keeps: Isa 883101 Sokoto (56ok + nigeriapostal), Karim
    // Lamido 888222 Taraba (56ok range + WPC listing + Mapanet filing).
    expect((string) $byCode->get('883101')->areaSourceId)->toBe('ng:state:sokoto')
        ->and((string) $byCode->get('888222')->areaSourceId)->toBe('ng:state:taraba');

    // Zamfara Kauran Namoda block (street mirrors: mycyber 882271,
    // headlines-today 882261).
    expect((string) $byCode->get('882212')->areaSourceId)->toBe('ng:state:zamfara')
        ->and((string) $byCode->get('882271')->areaSourceId)->toBe('ng:state:zamfara')
        ->and((string) $byCode->get('882285')->areaSourceId)->toBe('ng:state:zamfara');

    // Benue Kwande cluster (nigeriapostal Adikpo-area rows).
    expect((string) $byCode->get('982101')->areaSourceId)->toBe('ng:state:benue')
        ->and((string) $byCode->get('982104')->areaSourceId)->toBe('ng:state:benue');

    // Unanimous-block samples: Yobe 620-632, Taraba 660-672.
    expect((string) $byCode->get('622104')->areaSourceId)->toBe('ng:state:yobe')
        ->and((string) $byCode->get('631101')->areaSourceId)->toBe('ng:state:yobe')
        ->and((string) $byCode->get('670102')->areaSourceId)->toBe('ng:state:taraba')
        ->and((string) $byCode->get('672101')->areaSourceId)->toBe('ng:state:taraba');

    // Per-state primary counts (all 37 covered).
    $counts = $postcodes->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts)->toHaveCount(37)
        ->and($counts->get('ng:state:lagos'))->toBe(135)
        ->and($counts->get('ng:state:kano'))->toBe(10)
        ->and($counts->get('ng:state:sokoto'))->toBe(12)
        ->and($counts->get('ng:state:zamfara'))->toBe(13)
        ->and($counts->get('ng:state:rivers'))->toBe(16)
        ->and($counts->get('ng:state:taraba'))->toBe(26)
        ->and($counts->get('ng:state:yobe'))->toBe(30);
});
