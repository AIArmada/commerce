<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Vanuatu\VanuatuGeographyProvider;

it('ships 6 ISO-coded provinces with 67 level-2 areas', function (): void {
    $areas = app(VanuatuGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($areas->where('type', 'province'))->toHaveCount(6)
        ->and($l2)->toHaveCount(67)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('vu:province:malampa')->code)->toBe('MAP')
        ->and($byId->get('vu:province:penama')->code)->toBe('PAM')
        ->and($byId->get('vu:province:sanma')->code)->toBe('SAM')
        ->and($byId->get('vu:province:shefa')->code)->toBe('SEE')
        ->and($byId->get('vu:province:tafea')->code)->toBe('TAE')
        ->and($byId->get('vu:province:torba')->code)->toBe('TOB');
});

it('pins the verified VU tree of 10/10/10/18/12/7 councils', function (): void {
    $areas = app(VanuatuGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('parentSourceId', 'vu:province:malampa'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'vu:province:penama'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'vu:province:sanma'))->toHaveCount(10)
        ->and($areas->where('parentSourceId', 'vu:province:shefa'))->toHaveCount(18)
        ->and($areas->where('parentSourceId', 'vu:province:tafea'))->toHaveCount(12)
        ->and($areas->where('parentSourceId', 'vu:province:torba'))->toHaveCount(7);

    // B10 renames carried forward (census + COD + Statoids variants):
    // HASC codes stay with their divisions.
    expect($byId->get('vu:area_council:east-ambae')->code)->toBe('VU.PM.LT')
        ->and($byId->get('vu:area_council:north-maewo')->code)->toBe('VU.PM.BV')
        ->and($byId->get('vu:area_council:south-epi')->code)->toBe('VU.SE.YA')
        ->and($byId->get('vu:area_council:whitesands-tanna')->name)->toBe('Whitesands')
        ->and($byId->has('vu:area_council:bangan-vanua'))->toBeFalse()
        ->and($byId->has('vu:area_council:lungei-tagaro'))->toBeFalse()
        ->and($byId->has('vu:area_council:yarsu'))->toBeFalse()
        ->and($byId->has('vu:area_council:central-torba'))->toBeFalse();

    // B10 Torba: 7 island councils replace the 3 directional rows.
    expect($byId->get('vu:area_council:gaua')->parentSourceId)->toBe('vu:province:torba')
        ->and($byId->get('vu:area_council:vanua-lava')->parentSourceId)->toBe('vu:province:torba')
        ->and($byId->get('vu:area_council:merelava')->parentSourceId)->toBe('vu:province:torba');
});
