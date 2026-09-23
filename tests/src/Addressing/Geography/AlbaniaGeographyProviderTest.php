<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Albania\AlbaniaAddressFormatter;
use AIArmada\Addressing\Geography\Albania\AlbaniaGeographyProvider;

it('formats Albanian addresses with the postcode above the locality', function (): void {
    $formatted = app(AlbaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ruga Myslym Shyri',
        'line2' => 'Pallati 37 shkalla 4 apartamenti 15',
        'city' => 'TIRANA',
        'postcode' => '1001',
        'country_code' => 'AL',
    ]));

    expect($formatted)->toBe("Ruga Myslym Shyri\nPallati 37 shkalla 4 apartamenti 15\n1001\nTIRANA\nAlbania");
});
it('formats Albanian addresses keeping the county below the locality', function (): void {
    $formatted = app(AlbaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ruga Myslym Shyri',
        'city' => 'Tirana',
        'state' => 'Tirana',
        'postcode' => '1001',
        'country_code' => 'AL',
    ]));

    expect($formatted)->toBe("Ruga Myslym Shyri\n1001\nTirana\nAlbania");
});

it('ships 61 municipalitys under countys with parent links', function (): void {
    $areas = app(AlbaniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(61)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('al:municipality:tirana')->name)->toBe('Tirana')
        ->and($byId->get('al:municipality:durres')->name)->toBe('Durrës')
        ->and($byId->get('al:municipality:shkoder')->name)->toBe('Shkodër');
});

it('labels counties Qark and municipalities Bashki', function (): void {
    $provider = app(AlbaniaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['county' => 'Qark', 'municipality' => 'Bashki'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
