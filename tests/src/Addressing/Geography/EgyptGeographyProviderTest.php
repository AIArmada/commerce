<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Egypt\EgyptGeographyProvider;

it('pins the verified Egypt tree of 27 governorates and 365 COD-AB districts', function (): void {
    $areas = app(EgyptGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'governorate'))->toHaveCount(27)
        ->and($areas->where('type', 'district'))->toHaveCount(365)
        ->and($areas->where('parentSourceId', 'eg:governorate:cairo'))->toHaveCount(42)
        ->and($areas->where('parentSourceId', 'eg:governorate:giza'))->toHaveCount(22)
        ->and($areas->where('parentSourceId', 'eg:governorate:sharqia'))->toHaveCount(22)
        ->and($areas->where('parentSourceId', 'eg:governorate:dakahlia'))->toHaveCount(21)
        ->and($areas->where('parentSourceId', 'eg:governorate:sohag'))->toHaveCount(20)
        ->and($areas->where('parentSourceId', 'eg:governorate:alexandria'))->toHaveCount(19)
        ->and($areas->where('parentSourceId', 'eg:governorate:asyut'))->toHaveCount(15)
        ->and($areas->where('parentSourceId', 'eg:governorate:suez'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'eg:governorate:luxor'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'eg:governorate:new-valley'))->toHaveCount(4);

    // ISO 3166-2:EG codes (common-English display names, COD spellings
    // kept verbatim at district level: Suhag, Qina, Zaqaziq).
    expect($byId->get('eg:governorate:cairo')->code)->toBe('C')
        ->and($byId->get('eg:governorate:giza')->code)->toBe('GZ')
        ->and($byId->get('eg:governorate:sharqia')->code)->toBe('SHR')
        ->and($byId->get('eg:governorate:sohag')->code)->toBe('SHG')
        ->and($byId->get('eg:governorate:qena')->code)->toBe('KN')
        ->and($byId->get('eg:governorate:luxor')->code)->toBe('LX')
        ->and($byId->get('eg:governorate:suez')->code)->toBe('SUZ');

    // COD-AB p-code anchors: Luxor qism/markaz twins, police units,
    // Zemam residuals, COD transliteration rows.
    expect($byId->get('eg:district:suez')->code)->toBe('EG0401')
        ->and($byId->get('eg:district:luxor')->code)->toBe('EG2901')
        ->and($byId->get('eg:district:luxor:luxor')->code)->toBe('EG2902')
        ->and($byId->get('eg:district:luxor:luxor')->parentSourceId)->toBe('eg:governorate:luxor')
        ->and($byId->get('eg:district:port-suez-police-department')->code)->toBe('EG0406')
        ->and($byId->get('eg:district:zemam-out')->code)->toBe('EG0100')
        ->and($byId->get('eg:district:suhag')->parentSourceId)->toBe('eg:governorate:sohag')
        ->and($byId->get('eg:district:qina')->parentSourceId)->toBe('eg:governorate:qena')
        ->and($byId->get('eg:district:zaqaziq')->code)->toBe('EG1303');
});
