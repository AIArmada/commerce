<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Uruguay\UruguayGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Uruguay tree of 19 departments and 136 municipalities', function (): void {
    $areas = app(UruguayGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(19)
        ->and($areas->where('level', 2))->toHaveCount(136);

    // ISO 3166-2:UY spot pins (prefetch + UPU uryEn profile agree on all 19).
    expect($byId->get('uy:department:artigas')->code)->toBe('AR')
        ->and($byId->get('uy:department:flores')->code)->toBe('FS')
        ->and($byId->get('uy:department:florida')->code)->toBe('FD')
        ->and($byId->get('uy:department:montevideo')->code)->toBe('MO')
        ->and($byId->get('uy:department:treinta-y-tres')->code)->toBe('TT');

    // 11 post-2020 adds (OPP Mapa Municipios 2025 + ES tables + Medios Públicos).
    foreach (['ansina', 'del-andaluz', 'juanico', 'conchillas', 'cufre', 'piraraja', 'zapican',
        'paysandu:cerro-chato', 'el-eucalipto', 'villa-soriano', 'villa-caraguata'] as $slug) {
        expect($byId->has('uy:municipality:' . $slug))->toBeTrue();
    }
    expect($byId->get('uy:municipality:ansina')->name)->toBe('Ansina')
        ->and($byId->get('uy:municipality:ansina')->parentSourceId)->toBe('uy:department:tacuarembo')
        ->and($byId->get('uy:municipality:paysandu:cerro-chato')->name)->toBe('Cerro Chato')
        ->and($byId->get('uy:municipality:paysandu:cerro-chato')->parentSourceId)->toBe('uy:department:paysandu')
        ->and($byId->get('uy:municipality:villa-caraguata')->name)->toBe('Villa Caraguatá');

    // Per-department L2 counts post-fix (OPP box counts).
    $l2 = $areas->where('level', 2)->groupBy('parentSourceId');
    expect($l2->get('uy:department:canelones'))->toHaveCount(32)
        ->and($l2->get('uy:department:colonia'))->toHaveCount(13)
        ->and($l2->get('uy:department:lavalleja'))->toHaveCount(6)
        ->and($l2->get('uy:department:paysandu'))->toHaveCount(9)
        ->and($l2->get('uy:department:soriano'))->toHaveCount(5)
        ->and($l2->get('uy:department:tacuarembo'))->toHaveCount(4)
        ->and($l2->get('uy:department:cerro-largo'))->toHaveCount(16)
        ->and($l2->get('uy:department:treinta-y-tres'))->toHaveCount(6);

    // Spanish official labels (OPP map + ES tables).
    expect($byId->get('uy:municipality:municipality-b')->name)->toBe('Municipio B')
        ->and($byId->get('uy:municipality:municipality-ch')->name)->toBe('Municipio CH')
        ->and($byId->get('uy:municipality:miguelete')->name)->toBe('Colonia Miguelete')
        ->and($byId->get('uy:municipality:enrique-martinez')->name)->toBe('Enrique Martínez');
});

it('bundles 124 Uruguay postcodes with 351 legs and corrected primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('UY', $dir . '/uruguay-postal-codes.csv', $dir . '/uruguay-postal-code-areas.csv', 'aiarmada.addressing.uruguay');

    $postcodes = $source->postalCodes()->collect();

    // Correo listadoCP (124 codes) == bundled set exactly; GeoNames covers 122/124
    // (20100 Punta del Este town + 27500 India Muerta zone are Correo-official, GN-stale).
    expect($postcodes)->toHaveCount(351);

    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(124)
        ->and($byCode->filter(fn ($legs) => $legs->count() > 1))->toHaveCount(93);

    $primaryOf = fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Flipped primaries (seat/vote/majority evidence in the B14 verdict).
    expect($primaryOf('37000'))->toBe('uy:department:cerro-largo')
        ->and($primaryOf('50200'))->toBe('uy:municipality:belen')
        ->and($primaryOf('15700'))->toBe('uy:municipality:toledo')
        ->and($primaryOf('30100'))->toBe('uy:municipality:solis-de-mataojo')
        ->and($primaryOf('91200'))->toBe('uy:municipality:san-bautista')
        ->and($primaryOf('91500'))->toBe('uy:municipality:sauce')
        ->and($primaryOf('12400'))->toBe('uy:municipality:municipality-g')
        ->and($primaryOf('12500'))->toBe('uy:municipality:municipality-g');

    // New-municipality legs.
    $has = fn (string $code, string $area): bool => $byCode->get($code)
        ->contains(fn ($leg): bool => (string) $leg->areaSourceId === $area);
    expect($has('15700', 'uy:municipality:del-andaluz'))->toBeTrue()
        ->and($has('90000', 'uy:municipality:juanico'))->toBeTrue()
        ->and($has('70000', 'uy:municipality:conchillas'))->toBeTrue()
        ->and($has('70300', 'uy:municipality:cufre'))->toBeTrue()
        ->and($has('30000', 'uy:municipality:piraraja'))->toBeTrue()
        ->and($has('34100', 'uy:municipality:zapican'))->toBeTrue()
        ->and($has('60000', 'uy:municipality:paysandu:cerro-chato'))->toBeTrue()
        ->and($has('60000', 'uy:municipality:el-eucalipto'))->toBeTrue()
        ->and($has('75100', 'uy:municipality:villa-soriano'))->toBeTrue()
        ->and($has('45000', 'uy:municipality:ansina'))->toBeTrue()
        ->and($has('45000', 'uy:municipality:villa-caraguata'))->toBeTrue()
        ->and($has('37000', 'uy:municipality:las-canas'))->toBeTrue();

    // Dropped legs stay gone (zero-support joins).
    expect($has('55000', 'uy:municipality:barros-blancos'))->toBeFalse()
        ->and($has('15000', 'uy:municipality:toledo'))->toBeFalse()
        ->and($has('15300', 'uy:municipality:empalme-olmos'))->toBeFalse()
        ->and($has('15800', 'uy:municipality:colonia-nicolich'))->toBeFalse()
        ->and($has('15800', 'uy:municipality:pando'))->toBeFalse()
        ->and($has('15900', 'uy:municipality:empalme-olmos'))->toBeFalse()
        ->and($has('37100', 'uy:municipality:las-canas'))->toBeFalse()
        ->and($has('12800', 'uy:municipality:municipality-d'))->toBeFalse()
        ->and($has('91500', 'uy:municipality:ciudad-de-la-costa'))->toBeFalse()
        ->and($has('91500', 'uy:municipality:empalme-olmos'))->toBeFalse();

    // UPU addressed-usage anchors hold their primaries.
    expect($primaryOf('11600'))->toBe('uy:municipality:municipality-ch')
        ->and($primaryOf('12900'))->toBe('uy:municipality:municipality-g')
        ->and($primaryOf('15600'))->toBe('uy:municipality:pando')
        ->and($primaryOf('90000'))->toBe('uy:municipality:canelones')
        ->and($primaryOf('80300'))->toBe('uy:municipality:ecilda-paullier');

    // Oct-2026 open-log #15 retry: 27 of 30 holds resolved (5 flips +
    // 7 drops + 16 adds). Flipped primaries:
    expect($primaryOf('12100'))->toBe('uy:municipality:municipality-f')
        ->and($primaryOf('12000'))->toBe('uy:municipality:municipality-d')
        ->and($primaryOf('35200'))->toBe('uy:municipality:cerro-chato')
        ->and($primaryOf('60200'))->toBe('uy:municipality:paysandu:quebracho');

    // Added legs (H1/H2/H3 Montevideo + H9/H11/H18/H30 interior).
    expect($has('12000', 'uy:municipality:municipality-c'))->toBeTrue()
        ->and($has('11900', 'uy:municipality:municipality-g'))->toBeTrue()
        ->and($has('11300', 'uy:municipality:municipality-b'))->toBeTrue()
        ->and($has('11600', 'uy:municipality:municipality-e'))->toBeTrue()
        ->and($has('11600', 'uy:municipality:municipality-c'))->toBeTrue()
        ->and($has('11400', 'uy:municipality:municipality-d'))->toBeTrue()
        ->and($has('11800', 'uy:municipality:municipality-ch'))->toBeTrue()
        ->and($has('11700', 'uy:municipality:municipality-d'))->toBeTrue()
        ->and($has('11700', 'uy:municipality:municipality-a'))->toBeTrue()
        ->and($has('30100', 'uy:municipality:soca'))->toBeTrue()
        ->and($has('70800', 'uy:municipality:conchillas'))->toBeTrue()
        ->and($has('35200', 'uy:department:treinta-y-tres'))->toBeTrue()
        ->and($has('60200', 'uy:municipality:chapicuy'))->toBeTrue()
        ->and($has('60200', 'uy:municipality:paysandu:cerro-chato'))->toBeTrue();

    // Retry drops stay gone (zero-support legs).
    expect($has('12000', 'uy:municipality:municipality-e'))->toBeFalse()
        ->and($has('20500', 'uy:municipality:lascano'))->toBeFalse()
        ->and($has('20500', 'uy:department:maldonado'))->toBeFalse()
        ->and($has('33000', 'uy:municipality:vergara'))->toBeFalse()
        ->and($has('50000', 'uy:municipality:belen'))->toBeFalse()
        ->and($has('70000', 'uy:municipality:tarariras'))->toBeFalse()
        ->and($has('60200', 'uy:department:paysandu'))->toBeFalse();

    // Still-held keeps + vindicated primaries (H6/H7/H13-SJ/H14/H24/H26/
    // H27-carmelo + Lezica G-single).
    expect($primaryOf('40200'))->toBe('uy:department:artigas')
        ->and($primaryOf('15500'))->toBe('uy:municipality:pando')
        ->and($primaryOf('34200'))->toBe('uy:department:florida')
        ->and($primaryOf('45100'))->toBe('uy:department:rio-negro')
        ->and($primaryOf('98000'))->toBe('uy:department:florida')
        ->and($has('37000', 'uy:municipality:tupambae'))->toBeTrue()
        ->and($has('91500', 'uy:municipality:san-jacinto'))->toBeTrue()
        ->and($has('70000', 'uy:municipality:carmelo'))->toBeTrue()
        ->and($byCode->get('12500')->count())->toBe(1);
});
