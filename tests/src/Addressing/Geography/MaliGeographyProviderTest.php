<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Mali\MaliGeographyProvider;

it('ships 19 regions plus Bamako with the national 9/10 numbering', function (): void {
    $areas = app(MaliGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas->where('level', 1))->toHaveCount(20);

    $byId = $areas->keyBy('sourceId');

    expect($byId->get('ml:region:menaka')->code)->toBe('10')
        ->and($byId->get('ml:region:taoudenit')->code)->toBe('9');

    $codes = $areas->mapWithKeys(fn ($area) => [$area->name => $area->code]);

    foreach (['Nioro' => '11', 'Kita' => '12', 'Dioila' => '13', 'Nara' => '14', 'Bougouni' => '15', 'Koutiala' => '16', 'San' => '17', 'Douentza' => '18', 'Bandiagara' => '19'] as $name => $code) {
        expect($codes->get($name))->toBe($code);
    }
});

it('pins the verified Mali tree of 159 cercles under regions with parent links', function (): void {
    $areas = app(MaliGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'cercle');

    expect($l2)->toHaveCount(159)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('parentSourceId', 'ml:region:gao'))->toHaveCount(16)
        ->and($areas->where('parentSourceId', 'ml:region:tombouctou'))->toHaveCount(13)
        ->and($areas->where('parentSourceId', 'ml:region:segou'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'ml:region:kayes'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'ml:region:bougouni'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'ml:region:kidal'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'ml:region:bandiagara'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'ml:region:san'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'ml:district:bamako'))->toHaveCount(0)
        ->and($byId->get('ml:cercle:kayes')->code)->toBe('0101')
        ->and($byId->get('ml:cercle:sadiola')->code)->toBe('0110')
        ->and($byId->get('ml:cercle:ansongo')->code)->toBe('0703');

    // Cercles-of-Mali oracle: Niéna fix (was Niéma), Anéfif /
    // Dialassagou / Taoudenni / Tombouctou / Kadiana keeps.
    expect($byId->get('ml:cercle:niema')->name)->toBe('Niéna')
        ->and($byId->get('ml:cercle:anefif')->name)->toBe('Anéfif')
        ->and($byId->get('ml:cercle:dialassagou')->name)->toBe('Dialassagou')
        ->and($byId->get('ml:cercle:taoudenni')->parentSourceId)->toBe('ml:region:taoudenit')
        ->and($byId->get('ml:cercle:tombouctou')->parentSourceId)->toBe('ml:region:tombouctou')
        ->and($byId->get('ml:cercle:kadiana')->parentSourceId)->toBe('ml:region:bougouni');
});
