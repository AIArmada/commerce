<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Benin\BeninGeographyProvider;

it('pins the verified Benin tree of 12 departments and 77 communes', function (): void {
    $areas = app(BeninGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'department'))->toHaveCount(12)
        ->and($areas->where('type', 'commune'))->toHaveCount(77)
        ->and($areas->where('parentSourceId', 'bj:department:atakora'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'bj:department:oueme'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'bj:department:zou'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'bj:department:atlantique'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'bj:department:borgou'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'bj:department:alibori'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'bj:department:littoral'))->toHaveCount(1);

    // ISO 3166-2:BJ codes (Atakora/Kouffo kept over ISO
    // Atacora/Couffo); official-truth renames: Péhunco, Covè,
    // Zagnanado, Ségbana, Porto-Novo.
    expect($byId->get('bj:department:atakora')->code)->toBe('AK')
        ->and($byId->get('bj:department:kouffo')->code)->toBe('KO')
        ->and($byId->get('bj:department:littoral')->code)->toBe('LI')
        ->and($byId->get('bj:commune:cotonou')->parentSourceId)->toBe('bj:department:littoral')
        ->and($byId->get('bj:commune:pehonko')->name)->toBe('Péhunco')
        ->and($byId->get('bj:commune:cove')->name)->toBe('Covè')
        ->and($byId->get('bj:commune:zangnanado')->name)->toBe('Zagnanado')
        ->and($byId->get('bj:commune:segbana')->name)->toBe('Ségbana')
        ->and($byId->get('bj:commune:porto-novo')->name)->toBe('Porto-Novo');
});
