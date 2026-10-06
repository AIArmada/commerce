<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Yemen\YemenGeographyProvider;

it('pins the verified Yemen tree of 21 governorates, 1 municipality, and 333 districts', function (): void {
    $areas = app(YemenGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'governorate'))->toHaveCount(21)
        ->and($areas->where('type', 'municipality'))->toHaveCount(1)
        ->and($areas->where('type', 'district'))->toHaveCount(333)
        ->and($areas->where('parentSourceId', 'ye:governorate:hajjah'))->toHaveCount(31)
        ->and($areas->where('parentSourceId', 'ye:governorate:hadhramaut'))->toHaveCount(28)
        ->and($areas->where('parentSourceId', 'ye:governorate:al-hudaydah'))->toHaveCount(26)
        ->and($areas->where('parentSourceId', 'ye:governorate:taizz'))->toHaveCount(23)
        ->and($areas->where('parentSourceId', 'ye:governorate:socotra'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'ye:municipality:amanat-al-asimah'))->toHaveCount(10);

    // ISO 3166-2:YE codes; SA is the Sanaa municipality, SU Socotra.
    expect($byId->get('ye:municipality:amanat-al-asimah')->code)->toBe('SA')
        ->and($byId->get('ye:governorate:socotra')->code)->toBe('SU')
        ->and($byId->get('ye:governorate:hadhramaut')->code)->toBe('HD')
        ->and($byId->get('ye:governorate:sanaa')->code)->toBe('SN')
        ->and($byId->get('ye:governorate:taizz')->code)->toBe('TA')
        ->and($byId->get('ye:district:hidaybu')->parentSourceId)->toBe('ye:governorate:socotra')
        ->and($byId->get('ye:district:old-city')->parentSourceId)->toBe('ye:municipality:amanat-al-asimah');
});
