<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Kuwait\KuwaitGeographyProvider;

it('pins the verified Kuwait tree of 6 governorates and 135 areas', function (): void {
    $areas = app(KuwaitGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'governorate'))->toHaveCount(6)
        ->and($areas->where('type', 'area'))->toHaveCount(135)
        ->and($areas->where('parentSourceId', 'kw:governorate:al-asimah'))->toHaveCount(32)
        ->and($areas->where('parentSourceId', 'kw:governorate:al-ahmadi'))->toHaveCount(29)
        ->and($areas->where('parentSourceId', 'kw:governorate:al-jahra'))->toHaveCount(24)
        ->and($areas->where('parentSourceId', 'kw:governorate:al-farwaniyah'))->toHaveCount(20)
        ->and($areas->where('parentSourceId', 'kw:governorate:hawalli'))->toHaveCount(17)
        ->and($areas->where('parentSourceId', 'kw:governorate:mubarak-al-kabeer'))->toHaveCount(13);

    // ISO 3166-2:KW codes; Areas-of-Kuwait oracle: Kuwait City
    // capital area under Al Asimah, governorate/area name twins
    // (Jahra, Hawalli) share names by design.
    expect($byId->get('kw:governorate:al-ahmadi')->code)->toBe('AH')
        ->and($byId->get('kw:governorate:al-asimah')->code)->toBe('KU')
        ->and($byId->get('kw:governorate:al-farwaniyah')->code)->toBe('FA')
        ->and($byId->get('kw:area:kuwait-city')->name)->toBe('Kuwait City')
        ->and($byId->get('kw:area:kuwait-city')->code)->toBeNull()
        ->and($byId->get('kw:area:kuwait-city')->parentSourceId)->toBe('kw:governorate:al-asimah')
        ->and($byId->get('kw:area:adailiya')->name)->toBe('Adailiya')
        ->and($byId->get('kw:area:abdulla-al-salem')->parentSourceId)->toBe('kw:governorate:al-asimah')
        ->and($byId->get('kw:area:jahra')->parentSourceId)->toBe('kw:governorate:al-jahra')
        ->and($byId->get('kw:area:hawalli')->parentSourceId)->toBe('kw:governorate:hawalli');
});
