<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\NewZealand\NewZealandGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('ships 67 territorial authorities under regions with parent links', function (): void {
    $areas = app(NewZealandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(67)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'district'))->toHaveCount(53)
        ->and($l2->where('type', 'city'))->toHaveCount(12)
        ->and($byId->get('nz:district:far-north')->parentSourceId)->toBe('nz:region:northland')
        ->and($byId->get('nz:district:waitaki')->parentSourceId)->toBe('nz:region:canterbury');
});

it('ships 1201 localities under their districts with postal locality roles', function (): void {
    $provider = app(NewZealandGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'locality'))->toHaveCount(1201)
        ->and($byId->get('nz:locality:summerhill')->parentSourceId)->toBe('nz:city:palmerston-north')
        ->and($byId->get('nz:locality:waioruarangi')->parentSourceId)->toBe('nz:district:kaikoura')
        ->and($byId->get('nz:locality:oamaru')->parentSourceId)->toBe('nz:district:waitaki');

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['nz:locality:summerhill'][0]['role'])->toBe('postal_locality');
});
