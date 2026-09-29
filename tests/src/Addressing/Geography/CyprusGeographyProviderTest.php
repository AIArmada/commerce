<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Cyprus\CyprusGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('ships 752 localities under their districts with postal locality roles', function (): void {
    $provider = app(CyprusGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'locality'))->toHaveCount(752)
        ->and($byId->get('cy:locality:paralimni')->parentSourceId)->toBe('cy:district:famagusta-magusa');

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['cy:locality:paralimni'][0]['role'])->toBe('postal_locality');
});
