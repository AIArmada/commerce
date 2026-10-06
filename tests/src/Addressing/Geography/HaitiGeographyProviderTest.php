<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Haiti\HaitiGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 10 ISO departments with 42 arrondissements under parent links', function (): void {
    $areas = app(HaitiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(52)
        ->and($l1)->toHaveCount(10)
        ->and($l2)->toHaveCount(42)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l1->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue();

    // Per-department arrondissement counts (WP/IHSI roster exact).
    $counts = $l2->countBy('parentSourceId');

    expect($counts->get('ht:department:artibonite'))->toBe(5)
        ->and($counts->get('ht:department:centre'))->toBe(4)
        ->and($counts->get('ht:department:grandanse'))->toBe(3)
        ->and($counts->get('ht:department:nippes'))->toBe(3)
        ->and($counts->get('ht:department:nord'))->toBe(7)
        ->and($counts->get('ht:department:nord-est'))->toBe(4)
        ->and($counts->get('ht:department:nord-ouest'))->toBe(3)
        ->and($counts->get('ht:department:ouest'))->toBe(5)
        ->and($counts->get('ht:department:sud'))->toBe(5)
        ->and($counts->get('ht:department:sud-est'))->toBe(3);
});

it('pins the 10 ISO 3166-2:HT department codes', function (): void {
    $byId = app(HaitiGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // HT-GA keeps the Haitian national spelling Grand'Anse; ISO 3166-2
    // alone spells it Grande'Anse (2015 spelling change).
    expect($byId->get('ht:department:artibonite')->code)->toBe('AR')
        ->and($byId->get('ht:department:centre')->code)->toBe('CE')
        ->and($byId->get('ht:department:grandanse')->code)->toBe('GA')
        ->and($byId->get('ht:department:grandanse')->name)->toBe('Grand\'Anse')
        ->and($byId->get('ht:department:nippes')->code)->toBe('NI')
        ->and($byId->get('ht:department:nord')->code)->toBe('ND')
        ->and($byId->get('ht:department:nord-est')->code)->toBe('NE')
        ->and($byId->get('ht:department:nord-ouest')->code)->toBe('NO')
        ->and($byId->get('ht:department:ouest')->code)->toBe('OU')
        ->and($byId->get('ht:department:sud')->code)->toBe('SD')
        ->and($byId->get('ht:department:sud-est')->code)->toBe('SE');
});

it('links the 240 HT postcodes to arrondissements with exactly one primary each', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('HT', $dir . '/haiti-postal-codes.csv', $dir . '/haiti-postal-code-areas.csv', 'aiarmada.addressing.haiti');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(240)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(240)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^HT\\d{4}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->relationshipType === 'served_by'))->toBeTrue()
        ->and($postcodes->where('isPrimary', true))->toHaveCount(240)
        ->and($postcodes->pluck('code')->contains('HT3408'))->toBeFalse();

    $primary = $postcodes->where('isPrimary', true)->keyBy->code;

    // B8 fill: 11 UPU-listed codes the GeoNames build missed (Parcelforce
    // Sep19 + Mapanet + postcode.info; HT6112 also Krezicart).
    expect((string) $primary->get('HT1131')->areaSourceId)->toBe('ht:arrondissement:cap-haitien')
        ->and((string) $primary->get('HT1212')->areaSourceId)->toBe('ht:arrondissement:acul-du-nord')
        ->and((string) $primary->get('HT2333')->areaSourceId)->toBe('ht:arrondissement:trou-du-nord')
        ->and((string) $primary->get('HT3223')->areaSourceId)->toBe('ht:arrondissement:saint-louis-du-nord')
        ->and((string) $primary->get('HT3311')->areaSourceId)->toBe('ht:arrondissement:mole-saint-nicolas')
        ->and((string) $primary->get('HT3312')->areaSourceId)->toBe('ht:arrondissement:mole-saint-nicolas')
        ->and((string) $primary->get('HT3341')->areaSourceId)->toBe('ht:arrondissement:mole-saint-nicolas')
        ->and((string) $primary->get('HT4330')->areaSourceId)->toBe('ht:arrondissement:saint-marc')
        ->and((string) $primary->get('HT6112')->areaSourceId)->toBe('ht:arrondissement:port-au-prince')
        ->and((string) $primary->get('HT6350')->areaSourceId)->toBe('ht:arrondissement:croix-des-bouquets')
        ->and((string) $primary->get('HT8316')->areaSourceId)->toBe('ht:arrondissement:aquin');

    // Cap-Haitien cluster (8) and Port-au-Prince cluster (33).
    expect($primary->where('areaSourceId', 'ht:arrondissement:cap-haitien')->keys()->sort()->values()->all())
        ->toBe(['HT1110', 'HT1111', 'HT1112', 'HT1113', 'HT1114', 'HT1120', 'HT1130', 'HT1131'])
        ->and($primary->where('areaSourceId', 'ht:arrondissement:port-au-prince')->keys()->sort()->values()->all())
        ->toBe([
            'HT6110', 'HT6111', 'HT6112', 'HT6113', 'HT6114', 'HT6115', 'HT6116',
            'HT6117', 'HT6118', 'HT6119', 'HT6120', 'HT6121', 'HT6122', 'HT6123',
            'HT6124', 'HT6125', 'HT6130', 'HT6131', 'HT6132', 'HT6133', 'HT6134',
            'HT6135', 'HT6136', 'HT6140', 'HT6141', 'HT6142', 'HT6143', 'HT6144',
            'HT6145', 'HT6146', 'HT6147', 'HT6150', 'HT6160',
        ]);

    // GeoNames-only keeps (Krezicart-confirmed sub-localities, absent from
    // the UPU list but structurally valid): never dropped on a tie.
    expect((string) $primary->get('HT4530')->areaSourceId)->toBe('ht:arrondissement:marmelade')
        ->and((string) $primary->get('HT6145')->areaSourceId)->toBe('ht:arrondissement:port-au-prince')
        ->and((string) $primary->get('HT6146')->areaSourceId)->toBe('ht:arrondissement:port-au-prince')
        ->and((string) $primary->get('HT6147')->areaSourceId)->toBe('ht:arrondissement:port-au-prince')
        ->and((string) $primary->get('HT8313')->areaSourceId)->toBe('ht:arrondissement:aquin');

    // 75-split keep: 752x is Baraderes arrondissement, not Anse-a-Veau
    // (UPU 42-district table lists 7520 BARADERES as its own district).
    expect((string) $primary->get('HT7520')->areaSourceId)->toBe('ht:arrondissement:baraderes')
        ->and((string) $primary->get('HT7521')->areaSourceId)->toBe('ht:arrondissement:baraderes');

    // Per-arrondissement primary counts (full table, 240 total).
    $counts = $primary->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts->get('ht:arrondissement:acul-du-nord'))->toBe(7)
        ->and($counts->get('ht:arrondissement:anse-a-veau'))->toBe(3)
        ->and($counts->get('ht:arrondissement:anse-d-hainault'))->toBe(5)
        ->and($counts->get('ht:arrondissement:aquin'))->toBe(7)
        ->and($counts->get('ht:arrondissement:arcahaie'))->toBe(4)
        ->and($counts->get('ht:arrondissement:bainet'))->toBe(2)
        ->and($counts->get('ht:arrondissement:baraderes'))->toBe(2)
        ->and($counts->get('ht:arrondissement:belle-anse'))->toBe(7)
        ->and($counts->get('ht:arrondissement:borgne'))->toBe(6)
        ->and($counts->get('ht:arrondissement:cap-haitien'))->toBe(8)
        ->and($counts->get('ht:arrondissement:cerca-la-source'))->toBe(4)
        ->and($counts->get('ht:arrondissement:chardonnieres'))->toBe(5)
        ->and($counts->get('ht:arrondissement:corail'))->toBe(4)
        ->and($counts->get('ht:arrondissement:coteaux'))->toBe(5)
        ->and($counts->get('ht:arrondissement:croix-des-bouquets'))->toBe(8)
        ->and($counts->get('ht:arrondissement:dessalines'))->toBe(5)
        ->and($counts->get('ht:arrondissement:fort-liberte'))->toBe(6)
        ->and($counts->get('ht:arrondissement:gonaives'))->toBe(4)
        ->and($counts->get('ht:arrondissement:grande-riviere-du-nord'))->toBe(2)
        ->and($counts->get('ht:arrondissement:gros-morne'))->toBe(4)
        ->and($counts->get('ht:arrondissement:hinche'))->toBe(6)
        ->and($counts->get('ht:arrondissement:jacmel'))->toBe(6)
        ->and($counts->get('ht:arrondissement:jeremie'))->toBe(8)
        ->and($counts->get('ht:arrondissement:la-gonave'))->toBe(2)
        ->and($counts->get('ht:arrondissement:lascahobas'))->toBe(4)
        ->and($counts->get('ht:arrondissement:leogane'))->toBe(6)
        ->and($counts->get('ht:arrondissement:les-cayes'))->toBe(8)
        ->and($counts->get('ht:arrondissement:limbe'))->toBe(3)
        ->and($counts->get('ht:arrondissement:marmelade'))->toBe(3)
        ->and($counts->get('ht:arrondissement:miragoane'))->toBe(5)
        ->and($counts->get('ht:arrondissement:mirebalais'))->toBe(5)
        ->and($counts->get('ht:arrondissement:mole-saint-nicolas'))->toBe(7)
        ->and($counts->get('ht:arrondissement:ouanaminthe'))->toBe(3)
        ->and($counts->get('ht:arrondissement:plaisance'))->toBe(3)
        ->and($counts->get('ht:arrondissement:port-au-prince'))->toBe(33)
        ->and($counts->get('ht:arrondissement:port-de-paix'))->toBe(7)
        ->and($counts->get('ht:arrondissement:port-salut'))->toBe(3)
        ->and($counts->get('ht:arrondissement:saint-louis-du-nord'))->toBe(5)
        ->and($counts->get('ht:arrondissement:saint-marc'))->toBe(7)
        ->and($counts->get('ht:arrondissement:saint-raphael'))->toBe(5)
        ->and($counts->get('ht:arrondissement:trou-du-nord'))->toBe(8)
        ->and($counts->get('ht:arrondissement:vallieres'))->toBe(5);
});

it('normalises bare domestic postcodes with the HT prefix', function (): void {
    $provider = app(HaitiGeographyProvider::class);

    expect($provider->postalCodeLookupKeys('6112'))->toBe(['6112', 'HT6112'])
        ->and($provider->postalCodeLookupKeys('HT6112'))->toBe(['HT6112'])
        ->and($provider->postalCodeLookupKeys('ht4330'))->toBe(['HT4330']);
});
