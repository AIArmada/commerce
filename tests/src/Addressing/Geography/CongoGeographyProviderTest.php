<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Congo\CongoGeographyProvider;

it('ships the 15 departments including the October 2024 trio', function (): void {
    $areas = app(CongoGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas->where('level', 1))->toHaveCount(15);

    $byId = $areas->keyBy('sourceId');

    expect($byId->get('cg:department:congo-oubangui')->name)->toBe('Congo-Oubangui')
        ->and($byId->get('cg:department:congo-oubangui')->code)->toBe('17')
        ->and($byId->get('cg:department:djoue-lefini')->name)->toBe('Djoué-Léfini')
        ->and($byId->get('cg:department:djoue-lefini')->code)->toBe('18')
        ->and($byId->get('cg:department:nkeni-alima')->name)->toBe('Nkéni-Alima')
        ->and($byId->get('cg:department:nkeni-alima')->code)->toBe('19');
});

it('matches the October 2024 laws: 92 districts, Ollombo moved, Odziba/Bouemba/Ile Mbamou present', function (): void {
    $areas = app(CongoGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas->where('level', 2))->toHaveCount(92);

    $byId = $areas->keyBy('sourceId');

    // Law 26-2024: Ollombo belongs to Nkéni-Alima, not Plateaux.
    expect($byId->get('cg:district:ollombo')->parentSourceId)->toBe('cg:department:nkeni-alima');
    // Law 24/25-2024: Odziba district created under Djoué-Léfini.
    expect($byId->get('cg:district:odziba')->parentSourceId)->toBe('cg:department:djoue-lefini');
    // Law 32-2024: Bouemba is the sixth Plateaux district.
    expect($byId->get('cg:district:bouemba')->parentSourceId)->toBe('cg:department:plateaux');
    // Law 29-2024 (+ 2011 creation law): Ile Mbamou district under Brazzaville.
    expect($byId->get('cg:district:ile-mbamou')->parentSourceId)->toBe('cg:department:brazzaville');
});

it('uses the Journal Officiel spellings for the redefined departments', function (): void {
    $areas = app(CongoGeographyProvider::class)->addressAreaSource()->areas();
    $byId = $areas->keyBy('sourceId');

    expect($byId->get('cg:district:vinza')->name)->toBe('Vinza') // Law 25 (Statoids/FR-WP: Vindza)
        ->and($byId->get('cg:district:ongoni')->name)->toBe('Ongoni') // Law 26 (WP: Ongogni)
        ->and($byId->get('cg:district:makotipoko')->name)->toBe('Makotipoko') // Law 26 (EN-WP: Makotimpoko)
        ->and($byId->get('cg:district:bouaniela')->name)->toBe('Bouaniéla') // Law 31 (WP: Bouanéla)
        ->and($byId->get('cg:district:mbandzandounga')->name)->toBe('Mbandza-Ndounga'); // Law 33 (was Mbanza–Ndounga)
});
