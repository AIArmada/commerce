<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\FaroeIslands\FaroeIslandsGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Faroe Islands tree of 6 regions and 29 municipalities', function (): void {
    $areas = app(FaroeIslandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'region'))->toHaveCount(6)
        ->and($areas->where('type', 'municipality'))->toHaveCount(29)
        ->and($areas->where('level', 1))->toHaveCount(6)
        ->and($areas->where('level', 2))->toHaveCount(29);

    // No ISO 3166-2:FO codes exist; the 6 sýslur regions carry
    // dataset codes and the 29 kommunur names match the WP
    // Municipalities roster 29/29 with island-consistent parents.
    expect($byId->get('fo:region:eysturoy')->code)->toBe('EY')
        ->and($byId->get('fo:region:northern-isles')->name)->toBe('Northern Isles')
        ->and($byId->get('fo:municipality:torshavn')->parentSourceId)->toBe('fo:region:streymoy')
        ->and($byId->get('fo:municipality:klaksvik')->parentSourceId)->toBe('fo:region:northern-isles')
        ->and($byId->get('fo:municipality:sunda')->parentSourceId)->toBe('fo:region:eysturoy')
        ->and($byId->get('fo:municipality:skuvoy')->parentSourceId)->toBe('fo:region:sandoy')
        ->and($byId->get('fo:municipality:porkeri')->name)->toBe('Porkeri');
});

it('bundles the 118 FO delivery postcodes across 119 municipality links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('FO', $dir . '/faroe-islands-postal-codes.csv', $dir . '/faroe-islands-postal-code-areas.csv', 'aiarmada.addressing.faroe_islands');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(119)
        ->and($postcodes->filter(static fn ($row): bool => $row->isPrimary))->toHaveCount(118);

    $byCode = $postcodes->groupBy->code;

    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Tórshavn cluster: capital + Argir/Hoyvík suburbs, Nólsoy,
    // Hestur, Koltur, Kollafjørður hamlets (da/fo Posta tables +
    // GeoNames FO dump + WP Towns agree).
    expect($primary('FO-100'))->toBe('fo:municipality:torshavn')
        ->and($primary('FO-160'))->toBe('fo:municipality:torshavn')
        ->and($primary('FO-188'))->toBe('fo:municipality:torshavn')
        ->and($primary('FO-270'))->toBe('fo:municipality:torshavn')
        ->and($primary('FO-280'))->toBe('fo:municipality:torshavn')
        ->and($primary('FO-285'))->toBe('fo:municipality:torshavn')
        ->and($primary('FO-410'))->toBe('fo:municipality:torshavn')
        ->and($primary('FO-416'))->toBe('fo:municipality:torshavn');

    // FO-485 Skálafjørður spans Runavík (primary) and Eystur
    // (secondary); WP Towns lists both legs.
    expect($primary('FO-485'))->toBe('fo:municipality:runavik')
        ->and($byCode->get('FO-485'))->toHaveCount(2)
        ->and((string) $byCode->get('FO-485')->firstWhere('isPrimary', false)->areaSourceId)->toBe('fo:municipality:eystur');

    // Off-town codes: Gøta district code, Nes (Vágur), Stóra Dímun
    // in Skúvoy; Sandavágur in Vágar (WP Towns Vágur cell is a typo,
    // Miðvágur row + roster confirm Vágar).
    expect($primary('FO-510'))->toBe('fo:municipality:eystur')
        ->and($primary('FO-925'))->toBe('fo:municipality:vagur')
        ->and($primary('FO-286'))->toBe('fo:municipality:skuvoy')
        ->and($primary('FO-360'))->toBe('fo:municipality:vagar')
        ->and($primary('FO-700'))->toBe('fo:municipality:klaksvik')
        ->and($primary('FO-800'))->toBe('fo:municipality:tvoroyri')
        ->and($primary('FO-970'))->toBe('fo:municipality:sumba');

    // Held out: the 12 postboks codes (da postboks labels; each GN
    // row duplicates its delivery locality).
    expect($byCode->has('FO-110'))->toBeFalse()
        ->and($byCode->has('FO-165'))->toBeFalse()
        ->and($byCode->has('FO-215'))->toBeFalse()
        ->and($byCode->has('FO-355'))->toBeFalse()
        ->and($byCode->has('FO-375'))->toBeFalse()
        ->and($byCode->has('FO-405'))->toBeFalse()
        ->and($byCode->has('FO-515'))->toBeFalse()
        ->and($byCode->has('FO-535'))->toBeFalse()
        ->and($byCode->has('FO-610'))->toBeFalse()
        ->and($byCode->has('FO-710'))->toBeFalse()
        ->and($byCode->has('FO-810'))->toBeFalse()
        ->and($byCode->has('FO-910'))->toBeFalse();
});
