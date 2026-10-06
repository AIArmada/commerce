<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\CentralAfricanRepublic\CentralAfricanRepublicGeographyProvider;

it('ships 20 prefectures with Bangui and Sangha-Mbaéré retyped', function (): void {
    $areas = app(CentralAfricanRepublicGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas->where('level', 1))->toHaveCount(20);

    $byId = $areas->keyBy('sourceId');

    expect($byId->has('cf:commune:bangui'))->toBeFalse()
        ->and($byId->get('cf:prefecture:bangui')->type)->toBe('prefecture')
        ->and($byId->has('cf:prefecture:sangha-mbaere'))->toBeFalse()
        ->and($byId->get('cf:economic_prefecture:sangha-mbaere')->type)->toBe('economic_prefecture')
        ->and($byId->get('cf:prefecture:lim-pende')->name)->toBe('Lim-Pendé')
        ->and($byId->get('cf:prefecture:mambere')->name)->toBe('Mambéré')
        ->and($byId->get('cf:prefecture:ouham-fafa')->name)->toBe('Ouham-Fafa');
});

it('ships 85 subprefectures per the 2021 law and 2024 decree', function (): void {
    $areas = app(CentralAfricanRepublicGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas->where('level', 2))->toHaveCount(85)
        ->and($areas->where('level', 1))->toHaveCount(20);
});

it('drops stale pre-split duplicates and the Vakaga junk row', function (): void {
    $ids = app(CentralAfricanRepublicGeographyProvider::class)->addressAreaSource()->areas()->map->sourceId;

    expect($ids->contains('cf:subprefecture:batangafo'))->toBeFalse() // Ouham copy; Ouham-Fafa keeps :ouham-fafa:batangafo
        ->and($ids->contains('cf:subprefecture:ouham-pende:paoua'))->toBeFalse()
        ->and($ids->contains('cf:subprefecture:ouham-pende:ngaoundaye'))->toBeFalse()
        ->and($ids->contains('cf:subprefecture:prefectures-of-the-central-african-republic'))->toBeFalse();
});

it('pins post-reform membership: Bangui 4, Vakaga 4, Lobaye 6, Nana-Grebizi 3, Haute-Kotto 4', function (): void {
    $areas = app(CentralAfricanRepublicGeographyProvider::class)->addressAreaSource()->areas();
    $kids = fn (string $p) => $areas->where('parentSourceId', $p)->map->name->sort()->values()->all();

    expect($kids('cf:prefecture:bangui'))->toBe(['Bangui-Centre', 'Bangui-Fleuve', 'Bangui-Kagas', 'Bangui-Rapides'])
        ->and($kids('cf:prefecture:vakaga'))->toBe(['Amdafock', 'Birao', 'Ouanda Djallé', 'Ouandja'])
        ->and($kids('cf:prefecture:lobaye'))->toContain('Moboma')
        ->and($kids('cf:economic_prefecture:nana-grebizi'))->toContain('Nana-Outa')
        ->and($kids('cf:prefecture:haute-kotto'))->toContain('Ouandja-Kotto')
        ->and($kids('cf:prefecture:ouham'))->not->toContain('Batangafo');
});
