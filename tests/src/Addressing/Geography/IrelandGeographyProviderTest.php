<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Ireland\IrelandGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 26 counties under provinces with parent links', function (): void {
    $areas = app(IrelandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'county');

    expect($l2)->toHaveCount(26)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ie:county:carlow')->name)->toBe('Carlow')
        ->and($byId->get('ie:county:cavan')->parentSourceId)->toBe('ie:province:ulster');
});

it('pins the ISO 3166-2:IE tree with a single Dublin county', function (): void {
    $areas = app(IrelandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'province'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'ie:province:leinster'))->toHaveCount(12)
        ->and($areas->where('parentSourceId', 'ie:province:munster'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'ie:province:connacht'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'ie:province:ulster'))->toHaveCount(3);

    // ISO codes; LK/TA/WD are the ISO second parts (single-letter L/T/W are vehicle marks, not ISO).
    expect($byId->get('ie:province:connacht')->code)->toBe('C')
        ->and($byId->get('ie:county:dublin')->code)->toBe('D')
        ->and($byId->get('ie:county:cork')->code)->toBe('CO')
        ->and($byId->get('ie:county:limerick')->code)->toBe('LK')
        ->and($byId->get('ie:county:tipperary')->code)->toBe('TA')
        ->and($byId->get('ie:county:waterford')->code)->toBe('WD');

    // Dublin shape hold: one post-county row, no Fingal / South Dublin / Dun Laoghaire split.
    expect($areas->filter(fn ($a): bool => str_contains((string) $a->sourceId, 'dublin')))->toHaveCount(1)
        ->and($byId->get('ie:county:dublin')->parentSourceId)->toBe('ie:province:leinster');
});

it('bundles the verified 139-key Eircode routing overlay with exact primaries and two duals', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('IE', $dir . '/ireland-postal-codes.csv', $dir . '/ireland-postal-code-areas.csv', 'aiarmada.addressing.ireland');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(141)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(139)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(139)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^[A-Z]\\d[A-Z0-9]$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Crosswalk anchors from the wiki post-county x Autoaddress x OSM pass.
    expect($primary('A41'))->toBe('ie:county:dublin')
        ->and($primary('A63'))->toBe('ie:county:wicklow')
        ->and($primary('A91'))->toBe('ie:county:louth')
        ->and($primary('A94'))->toBe('ie:county:dublin')
        ->and($primary('A98'))->toBe('ie:county:wicklow')
        ->and($primary('C15'))->toBe('ie:county:meath')
        ->and($primary('D01'))->toBe('ie:county:dublin')
        ->and($primary('D6W'))->toBe('ie:county:dublin')
        ->and($primary('E91'))->toBe('ie:county:tipperary')
        ->and($primary('F92'))->toBe('ie:county:donegal')
        ->and($primary('H91'))->toBe('ie:county:galway')
        ->and($primary('K67'))->toBe('ie:county:dublin')
        ->and($primary('N41'))->toBe('ie:county:leitrim')
        ->and($primary('P31'))->toBe('ie:county:cork')
        ->and($primary('R95'))->toBe('ie:county:kilkenny')
        ->and($primary('T12'))->toBe('ie:county:cork')
        ->and($primary('V35'))->toBe('ie:county:limerick')
        ->and($primary('X91'))->toBe('ie:county:waterford')
        ->and($primary('Y14'))->toBe('ie:county:wicklow')
        ->and($primary('Y35'))->toBe('ie:county:wexford');

    // The two straddler duals: A82 Kells (Meath) primary + Kingscourt/Virginia (Cavan);
    // A92 Ardee/Drogheda (Louth) primary + Laytown-Bettystown-Mornington (Meath).
    $legs82 = $byCode->get('A82')
        ->mapWithKeys(static fn ($row): array => [(string) $row->areaSourceId => $row->isPrimary])
        ->all();
    expect($legs82)->toBe(['ie:county:meath' => true, 'ie:county:cavan' => false]);

    $legs92 = $byCode->get('A92')
        ->mapWithKeys(static fn ($row): array => [(string) $row->areaSourceId => $row->isPrimary])
        ->all();
    expect($legs92)->toBe(['ie:county:louth' => true, 'ie:county:meath' => false]);

    // No other key straddles: exactly A82 and A92 carry two legs.
    $multi = $byCode->filter(static fn ($rows): bool => $rows->count() > 1)->keys()->sort()->values()->all();
    expect($multi)->toBe(['A82', 'A92']);

    // Every county holds at least one routing key; Dublin holds the most.
    $counts = $postcodes->where('isPrimary', true)->countBy('areaSourceId');
    expect($counts)->toHaveCount(26)
        ->and($counts->get('ie:county:dublin'))->toBe(34)
        ->and($counts->get('ie:county:cork'))->toBe(23);
});

it('strips full 7-char Eircodes to the 3-char routing key at lookup', function (): void {
    $provider = app(IrelandGeographyProvider::class);

    expect($provider->postalCodeLookupKeys('A94 VP03'))->toBe(['A94VP03', 'A94'])
        ->and($provider->postalCodeLookupKeys('d02 eh42'))->toBe(['D02EH42', 'D02'])
        ->and($provider->postalCodeLookupKeys('D6W'))->toBe(['D6W']);
});
