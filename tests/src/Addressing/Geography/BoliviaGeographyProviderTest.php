<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Bolivia\BoliviaGeographyProvider;

it('ships 9 departments with ISO codes and 112 provinces', function (): void {
    $areas = app(BoliviaGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas->where('level', 1))->toHaveCount(9)
        ->and($areas->where('level', 2))->toHaveCount(112);

    $byId = $areas->keyBy('sourceId');

    expect($byId->get('bo:department:beni')->code)->toBe('B')
        ->and($byId->get('bo:department:cochabamba')->code)->toBe('C')
        ->and($byId->get('bo:department:chuquisaca')->code)->toBe('H')
        ->and($byId->get('bo:department:la-paz')->code)->toBe('L')
        ->and($byId->get('bo:department:pando')->code)->toBe('N')
        ->and($byId->get('bo:department:oruro')->code)->toBe('O')
        ->and($byId->get('bo:department:potosi')->code)->toBe('P')
        ->and($byId->get('bo:department:santa-cruz')->code)->toBe('S')
        ->and($byId->get('bo:department:tarija')->code)->toBe('T');
});

it('uses full official province names for the B13 renames', function (): void {
    $areas = app(BoliviaGeographyProvider::class)->addressAreaSource()->areas();
    $byId = $areas->keyBy('sourceId');

    expect($byId->get('bo:province:narciso-campero')->name)->toBe('Narciso Campero') // WP dept table + Statoids
        ->and($byId->get('bo:province:pedro-domingo-murillo')->name)->toBe('Pedro Domingo Murillo') // WP + Statoids
        ->and($byId->get('bo:province:sabaya')->name)->toBe('Sabaya') // renamed from Atahuallpa: EN article + ES WP + GeoNames 2025
        ->and($byId->get('bo:province:burdett-o-connor')->name)->toBe("Burdett O'Connor") // EN canonical redirect + ES infobox
        ->and($byId->get('bo:province:pantaleon-dalence')->name)->toBe('Pantaleón Dalence') // accent position
        ->and($byId->get('bo:province:tomas-barron')->name)->toBe('Tomás Barrón') // accent
        ->and($byId->get('bo:province:sud-chichas')->name)->toBe('Sud Chichas') // WP + Statoids
        ->and($byId->get('bo:province:sud-lipez')->name)->toBe('Sud Lípez'); // WP + Statoids
});

it('pins per-department province counts and the Cercado twins', function (): void {
    $areas = app(BoliviaGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas->where('parentSourceId', 'bo:department:la-paz'))->toHaveCount(20)
        ->and($areas->where('parentSourceId', 'bo:department:cochabamba'))->toHaveCount(16)
        ->and($areas->where('parentSourceId', 'bo:department:potosi'))->toHaveCount(16)
        ->and($areas->where('parentSourceId', 'bo:department:oruro'))->toHaveCount(16)
        ->and($areas->where('parentSourceId', 'bo:department:santa-cruz'))->toHaveCount(15)
        ->and($areas->where('parentSourceId', 'bo:department:chuquisaca'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'bo:department:beni'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'bo:department:tarija'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'bo:department:pando'))->toHaveCount(5);

    $byId = $areas->keyBy('sourceId');

    // Four departments have a Cercado; only Beni's is uninfixed.
    expect($byId->get('bo:province:cercado')->parentSourceId)->toBe('bo:department:beni')
        ->and($byId->get('bo:province:cochabamba:cercado')->parentSourceId)->toBe('bo:department:cochabamba')
        ->and($byId->get('bo:province:oruro:cercado')->parentSourceId)->toBe('bo:department:oruro')
        ->and($byId->get('bo:province:tarija:cercado')->parentSourceId)->toBe('bo:department:tarija');
});
