<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Comoros\ComorosGeographyProvider;

it('ships 16 prefectures under islands with parent links', function (): void {
    $areas = app(ComorosGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    // Loi N°11-006/AU Art. 7: Mwali 3, Ngazidja 8, Ndzuwani 5.
    expect($l2)->toHaveCount(16)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'km:island:moheli'))->toHaveCount(3)
        ->and($l2->where('parentSourceId', 'km:island:grande-comore'))->toHaveCount(8)
        ->and($l2->where('parentSourceId', 'km:island:anjouan'))->toHaveCount(5)
        ->and($byId->get('km:prefecture:moroni-bambao')->parentSourceId)->toBe('km:island:grande-comore')
        ->and($byId->get('km:prefecture:mutsamudu')->parentSourceId)->toBe('km:island:anjouan')
        ->and($byId->get('km:prefecture:fomboni')->parentSourceId)->toBe('km:island:moheli');
});

it('pins the verified island names and prefecture spellings', function (): void {
    $byId = app(ComorosGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // ISO 3166-2:KM island codes; prefecture names per Loi 11-006 Art. 7.
    expect($byId->get('km:island:anjouan')->code)->toBe('A')
        ->and($byId->get('km:island:grande-comore')->code)->toBe('G')
        ->and($byId->get('km:island:moheli')->name)->toBe('Mohéli')
        ->and($byId->get('km:prefecture:mitsamiouli-mboude')->name)->toBe('Mitsamiouli-Mboudé')
        ->and($byId->get('km:prefecture:mremani')->name)->toBe('Mrémani')
        ->and($byId->get('km:prefecture:itsandra-hamanvou')->name)->toBe('Itsandra-Hamanvou');
});
