<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Slovenia\SloveniaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('exposes corrected Slovenian municipality names', function (): void {
    $areas = app(SloveniaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('si:municipality:dobrovapolhov-gradec')->name)->toBe('Dobrova-Polhov Gradec')
        ->and($areas->get('si:municipality:dobrovapolhov-gradec')->code)->toBe('021')
        ->and($areas->get('si:municipality:miklavz-na-dravskem-polju')->name)->toBe('Miklavž na Dravskem polju')
        ->and($areas->get('si:municipality:miklavz-na-dravskem-polju')->code)->toBe('169')
        ->and($areas->get('si:municipality:sveti-jurij-v-slovenskih-goricah')->name)->toBe('Sveti Jurij v Slovenskih goricah')
        ->and($areas->get('si:municipality:sveti-jurij-v-slovenskih-goricah')->type)->toBe('municipality');
});

it('roles urban municipalities with the municipality selector', function (): void {
    $roles = app(SloveniaGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['si:urban_municipality:celje'][0]['role'])->toBe('municipality');
});

it('ships 212 ISO-coded municipalities with B16 counts', function (): void {
    $areas = app(SloveniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(212)
        ->and($areas->where('type', 'municipality'))->toHaveCount(200)
        ->and($areas->where('type', 'urban_municipality'))->toHaveCount(12)
        ->and($byId->get('si:urban_municipality:ljubljana')->code)->toBe('061')
        ->and($byId->get('si:urban_municipality:maribor')->code)->toBe('070')
        ->and($byId->get('si:municipality:ankaran')->code)->toBe('213')
        ->and($byId->get('si:municipality:kanal-ob-soci')->name)->toBe('Kanal ob Soči');
});

it('links 469 postcodes with the B16 Grobelno dual and PO-box exclusions', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('SI', $dir . '/slovenia-postal-codes.csv', $dir . '/slovenia-postal-code-areas.csv', 'aiarmada.addressing.slovenia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(469);

    $byCode = $postcodes->groupBy->code;

    // Grobelno dual (sl.wiki lists both settlements; Šentjur primary).
    expect($byCode->get('3231')->map->areaSourceId->sort()->values()->all())
        ->toBe(['si:municipality:sentjur', 'si:municipality:smarje-pri-jelsah'])
        ->and($byCode->get('3231')->where('isPrimary', true)->first()->areaSourceId)->toBe('si:municipality:sentjur');

    // PO-box / large-user / internal codes excluded.
    foreach (['1001', '1500', '2001', '2500', '3001', '4001', '5001', '6001', '8001', '9001', '1371', '6323'] as $bad) {
        expect($byCode->has($bad))->toBeFalse();
    }

    $primary = fn (string $code): string => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // Keeps (operator list + GN + OSM sample).
    expect($primary('1000'))->toBe('si:urban_municipality:ljubljana')
        ->and($primary('9246'))->toBe('si:municipality:razkrizje')
        ->and($primary('9263'))->toBe('si:municipality:kuzma')
        ->and($primary('5215'))->toBe('si:municipality:kanal-ob-soci')
        ->and($primary('4283'))->toBe('si:municipality:kranjska-gora');
});
