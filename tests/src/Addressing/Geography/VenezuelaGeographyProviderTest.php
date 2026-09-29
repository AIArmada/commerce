<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Venezuela\VenezuelaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('names the W federal dependency Dependencias Federales', function (): void {
    $area = app(VenezuelaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId->get('ve:federal_dependency:dependencias-federales');

    expect($area->name)->toBe('Dependencias Federales')
        ->and($area->code)->toBe('W');
});

it('roles the capital district with the state selector', function (): void {
    $roles = app(VenezuelaGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['ve:capital_district:distrito-capital'][0]['role'])->toBe('state')
        ->and($roles['ve:federal_dependency:dependencias-federales'][0]['role'])->toBe('federal_dependency');
});
