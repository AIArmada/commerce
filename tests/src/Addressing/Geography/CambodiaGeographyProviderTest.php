<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Cambodia\CambodiaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('roles Phnom Penh as a province and krong cities as districts', function (): void {
    $roles = app(CambodiaGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['kh:municipality:phnom-penh'][0]['role'])->toBe('province')
        ->and($roles['kh:municipality:poipet'][0]['role'])->toBe('district')
        ->and($roles['kh:district:mongkol-borey'][0]['role'])->toBe('district')
        ->and($roles['kh:section:chamkar-mon'][0]['role'])->toBe('district');
});
