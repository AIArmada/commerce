<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\France\FranceGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('names the 973 region Guyane with French Guiana as the English alias', function (): void {
    $areas = app(FranceGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $names = app(FranceGeographyProvider::class)->areaNames(new AddressCountry);

    expect($areas->get('fr:region:french-guiana')->name)->toBe('Guyane')
        ->and($names['fr:region:french-guiana'][0])->toBe(['name' => 'French Guiana', 'name_type' => 'alternative']);
});
