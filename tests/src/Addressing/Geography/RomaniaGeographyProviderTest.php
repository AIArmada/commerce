<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Romania\RomaniaGeographyProvider;

it('exposes corrected Romanian department names', function (): void {
    $areas = app(RomaniaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ro:department:arges')->name)->toBe('Argeș')
        ->and($areas->get('ro:department:arges')->code)->toBe('AG')
        ->and($areas->get('ro:department:arges')->type)->toBe('department')
        ->and($areas->get('ro:department:braila')->name)->toBe('Brăila')
        ->and($areas->get('ro:department:braila')->code)->toBe('BR');
});

it('ships 3186 communes, towns, municipalities and sectors under counties with parent links', function (): void {
    $areas = app(RomaniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(3186)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'commune'))->toHaveCount(2861)
        ->and($l2->where('type', 'town'))->toHaveCount(217)
        ->and($l2->where('type', 'municipality'))->toHaveCount(102)
        ->and($l2->where('type', 'sector'))->toHaveCount(6)
        ->and($byId->get('ro:town:baneasa')->parentSourceId)->toBe('ro:department:constanta')
        ->and($byId->get('ro:sector:sector-1')->code)->toBe('S1');
});

it('pins the B21 tree pass: 17 SIRUTA name fixes, Bucharest exonym held', function (): void {
    $areas = app(RomaniaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ro:commune:daia-romana')->name)->toBe('Daia Română')
        ->and($areas->get('ro:commune:darmanesti')->name)->toBe('Dârmănești')
        ->and($areas->get('ro:commune:cluj:sanmartin')->name)->toBe('Sânmărtin')
        ->and($areas->get('ro:commune:galautas')->name)->toBe('Gălăuțaș')
        ->and($areas->get('ro:municipality:piatra-neamt')->name)->toBe('Piatra-Neamț')
        ->and($areas->get('ro:town:piatra-olt')->name)->toBe('Piatra-Olt')
        ->and($areas->get('ro:commune:boldesti-gradistea')->name)->toBe('Boldești-Gradiștea')
        ->and($areas->get('ro:commune:sarmasag')->name)->toBe('Șărmășag')
        ->and($areas->get('ro:commune:tulcea:c-a-rosetti')->name)->toBe('C.A. Rosetti')
        ->and($areas->get('ro:commune:i-c-bratianu')->name)->toBe('I.C. Brătianu')
        ->and($areas->get('ro:commune:tulcea:smardan')->name)->toBe('Smârdan')
        ->and($areas->get('ro:commune:selaru-dambovita')->name)->toBe('Șelaru')
        ->and($areas->get('ro:commune:alexandru-ioan-cuza')->name)->toBe('Alexandru I. Cuza')
        ->and($areas->get('ro:commune:padina-mare')->name)->toBe('Pădina')
        ->and($areas->get('ro:municipality:rosiorii-de-vede')->name)->toBe('Roșiori de Vede')
        ->and($areas->get('ro:commune:baidaud')->name)->toBe('Beidaud')
        ->and($areas->get('ro:commune:mihai-kogalniceanu')->name)->toBe('Mihail Kogălniceanu')
        ->and($areas->get('ro:municipality:bucharest')->name)->toBe('Bucharest')
        ->and($areas->get('ro:commune:petreu')->name)->toBe('Petreu');
});
