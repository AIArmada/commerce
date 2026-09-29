<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Honduras\HondurasGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('names the island department Islas de la Bahía with a Bay Islands alias', function (): void {
    $areas = app(HondurasGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('hn:department:islas-de-la-bahia')->name)->toBe('Islas de la Bahía')
        ->and($areas->get('hn:department:islas-de-la-bahia')->code)->toBe('IB')
        ->and($areas->has('hn:department:bay-islands'))->toBeFalse();

    $names = app(HondurasGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['hn:department:islas-de-la-bahia'][0]['name'])->toBe('Bay Islands');
});
