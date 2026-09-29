<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SaintKittsAndNevis\SaintKittsAndNevisGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('ships 14 parishes under the two island states with parent links', function (): void {
    $areas = app(SaintKittsAndNevisGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(14)
        ->and($byId->get('kn:parish:christ-church-nichola-town')->parentSourceId)->toBe('kn:state:saint-kitts');
});

it('ships 92 villages under their parishes with village roles', function (): void {
    $provider = app(SaintKittsAndNevisGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'village'))->toHaveCount(92)
        ->and($byId->get('kn:village:sandy-point-town')->parentSourceId)->toBe('kn:parish:saint-anne-sandy-point')
        ->and($byId->get('kn:village:saint-james-windward:camps')->parentSourceId)->toBe('kn:parish:saint-james-windward');

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['kn:village:sandy-point-town'][0]['role'])->toBe('village')
        ->and($roles['kn:parish:saint-anne-sandy-point'][0]['role'])->toBe('parish');
});
