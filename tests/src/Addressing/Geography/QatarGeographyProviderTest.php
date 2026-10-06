<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Qatar\QatarGeographyProvider;

it('pins the verified Qatar tree of 8 municipalities and 90 zones', function (): void {
    $areas = app(QatarGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'municipality'))->toHaveCount(8)
        ->and($areas->where('type', 'zone'))->toHaveCount(90)
        ->and($areas->where('parentSourceId', 'qa:municipality:doha'))->toHaveCount(57)
        ->and($areas->where('parentSourceId', 'qa:municipality:al-rayyan'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'qa:municipality:al-wakrah'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'qa:municipality:al-sheehaniya'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'qa:municipality:al-khor'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'qa:municipality:al-shamal'))->toHaveCount(3);

    // ISO 3166-2:QA codes; 2015-census reservation anchors:
    // 69/70 Al Daayen, 71 Umm Salal, 96/97 Al Rayyan.
    expect($byId->get('qa:municipality:doha')->code)->toBe('DA')
        ->and($byId->get('qa:municipality:al-sheehaniya')->code)->toBe('SH')
        ->and($byId->get('qa:municipality:al-daayen')->code)->toBe('ZA')
        ->and($byId->get('qa:zone:doha:1')->code)->toBe('1')
        ->and($byId->get('qa:zone:doha:1')->parentSourceId)->toBe('qa:municipality:doha')
        ->and($byId->get('qa:zone:al-daayen:69')->parentSourceId)->toBe('qa:municipality:al-daayen')
        ->and($byId->get('qa:zone:al-rayyan:96')->parentSourceId)->toBe('qa:municipality:al-rayyan')
        ->and($byId->get('qa:zone:umm-salal:71')->parentSourceId)->toBe('qa:municipality:umm-salal');
});
