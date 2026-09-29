<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Serbia\SerbiaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('roles Belgrade as a city and county cities as municipalities', function (): void {
    $roles = app(SerbiaGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['rs:city:belgrade'][0]['role'])->toBe('city')
        ->and($roles['rs:city:bor'][0]['role'])->toBe('municipality');
});
