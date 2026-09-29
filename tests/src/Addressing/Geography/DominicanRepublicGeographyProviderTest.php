<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\DominicanRepublic\DominicanRepublicGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('ships 31 provinces plus the Distrito Nacional under regions with parent links', function (): void {
    $areas = app(DominicanRepublicGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(32)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('do:province:azua')->name)->toBe('Azua')
        ->and($byId->get('do:district:distrito-nacional')->parentSourceId)->toBe('do:region:ozama');
});

it('ships 158 municipalities under their provinces with postal locality roles', function (): void {
    $provider = app(DominicanRepublicGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'municipality'))->toHaveCount(158)
        ->and($byId->get('do:municipality:santiago-de-los-caballeros')->parentSourceId)->toBe('do:province:santiago')
        ->and($byId->get('do:municipality:higuey')->parentSourceId)->toBe('do:province:la-altagracia')
        ->and($byId->get('do:municipality:baitoa')->parentSourceId)->toBe('do:province:santiago');

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['do:municipality:santiago-de-los-caballeros'][0]['role'])->toBe('postal_locality');
});
