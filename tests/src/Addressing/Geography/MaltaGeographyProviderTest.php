<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Malta\MaltaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Malta tree of 68 local councils', function (): void {
    $areas = app(MaltaGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas)->toHaveCount(68)
        ->and($areas->where('type', 'local_council'))->toHaveCount(68)
        ->and($areas->where('level', 1))->toHaveCount(68);

    // ISO 3166-2:MT codes 01-68 complete.
    expect($areas->pluck('code')->sort()->values()->all())->toBe(
        array_map(static fn (int $i): string => sprintf('%02d', $i), range(1, 68))
    );

    // English-vs-Maltese exonym variants pin the same ISO entities.
    $byCode = $areas->keyBy->code;

    expect($byCode->get('06')->name)->toBe('Cospicua') // ISO Bormla
        ->and($byCode->get('20')->name)->toBe('Senglea') // ISO Isla
        ->and($byCode->get('45')->name)->toBe('Victoria') // ISO Rabat Ghawdex
        ->and($byCode->get('46')->name)->toBe('Rabat') // ISO Rabat Malta
        ->and($byCode->get('48')->name)->toBe("St. Julian's") // ISO San Giljan
        ->and($byCode->get('51')->name)->toBe("St. Paul's Bay") // ISO San Pawl il-Bahar
        ->and($byCode->get('65')->name)->toBe('Żebbuġ Gozo'); // ISO Żebbuġ Ghawdex
});

it('bundles the 27823 MaltaPost street codes as single-primary council links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MT', $dir . '/malta-postal-codes.csv', $dir . '/malta-postal-code-areas.csv', 'aiarmada.addressing.malta');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(27823)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(27823)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(27823)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^[A-Z]{3} \\d{4}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Valletta / Sliema / Victoria clusters + Rabat disambiguation.
    expect($primary('VLT 1000'))->toBe('mt:local_council:valletta')
        ->and($primary('VLT 1930'))->toBe('mt:local_council:valletta')
        ->and($primary('SLM 1000'))->toBe('mt:local_council:sliema') // UPU example
        ->and($primary('VCT 1000'))->toBe('mt:local_council:victoria') // Rabat Gozo
        ->and($primary('RBT 1000'))->toBe('mt:local_council:rabat') // Rabat Malta
        ->and($primary('HMR 1420'))->toBe('mt:local_council:amrun');

    // Sub-locality -> parent council mappings.
    expect($primary('SGN 4011'))->toBe('mt:local_council:san-gwann') // Kappara
        ->and($primary('PTA 1010'))->toBe('mt:local_council:pieta') // Gwardamanga
        ->and($primary('RBT 4100'))->toBe('mt:local_council:rabat') // Bahrija
        ->and($primary('XLN 1010'))->toBe('mt:local_council:munxar') // Xlendi
        ->and($primary('MFN 1010'))->toBe('mt:local_council:zebbug-gozo') // Marsalforn
        ->and($primary('KMN 1010'))->toBe('mt:local_council:gajnsielem') // Comino
        ->and($primary('MSD 1820'))->toBe('mt:local_council:msida') // Swatar (Msida side)
        ->and($primary('BKR 4010'))->toBe('mt:local_council:birkirkara') // Swatar (BKR side)
        ->and($primary('KCM 1100'))->toBe('mt:local_council:kercem'); // Santa Lucija hamlet

    // Commercial / special prefixes + CBD three-council split.
    expect($primary('MEC 0001'))->toBe('mt:local_council:pieta')
        ->and($primary('SPK 1000'))->toBe('mt:local_council:st-julians')
        ->and($primary('SCM 1001'))->toBe('mt:local_council:kalkara') // Smart City
        ->and($primary('MTP 1001'))->toBe('mt:local_council:marsa') // MaltaPost HQ
        ->and($primary('CBD 1010'))->toBe('mt:local_council:birkirkara')
        ->and($primary('CBD 4060'))->toBe('mt:local_council:santa-venera')
        ->and($primary('CBD 5020'))->toBe('mt:local_council:qormi')
        ->and($primary('CBD 5060'))->toBe('mt:local_council:santa-venera'); // dual live, primary kept

    // B9 churn fix: 4 retired codes gone, 4 new codes linked.
    expect($byCode->has('GZR 1564'))->toBeFalse()
        ->and($byCode->has('MXK 4084'))->toBeFalse()
        ->and($byCode->has('RBT 4104'))->toBeFalse()
        ->and($byCode->has('RBT 4105'))->toBeFalse()
        ->and($primary('MXK 4081'))->toBe('mt:local_council:marsaxlokk')
        ->and($primary('RBT 4120'))->toBe('mt:local_council:rabat')
        ->and($primary('RBT 4121'))->toBe('mt:local_council:rabat')
        ->and($primary('XBX 1096'))->toBe('mt:local_council:ta-xbiex');

    // Per-council coverage: all 68 councils, largest + smallest + moved counts.
    $counts = $postcodes->where('isPrimary', true)->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts)->toHaveCount(68)
        ->and($counts->get('mt:local_council:birkirkara'))->toBe(1187)
        ->and($counts->get('mt:local_council:mosta'))->toBe(1170)
        ->and($counts->get('mt:local_council:mdina'))->toBe(59)
        ->and($counts->get('mt:local_council:fontana'))->toBe(57)
        ->and($counts->get('mt:local_council:gzira'))->toBe(252) // -1 retired
        ->and($counts->get('mt:local_council:ta-xbiex'))->toBe(140); // +1 new
});
