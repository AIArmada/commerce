<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SierraLeone\SierraLeoneAddressFormatter;
use AIArmada\Addressing\Geography\SierraLeone\SierraLeoneGeographyProvider;

it('formats Sierra Leonean addresses without a postcode system', function (): void {
    $formatted = app(SierraLeoneAddressFormatter::class)->format(AddressData::from([
        'line1' => '7A Ross Road Cline',
        'city' => 'FREETOWN',
        'country_code' => 'SL',
    ]));

    expect($formatted)->toBe("7A Ross Road Cline\nFREETOWN\nSierra Leone");
});
it('formats Sierra Leonean addresses with the province below the locality', function (): void {
    $formatted = app(SierraLeoneAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Bojon Street',
        'city' => 'Bo',
        'state' => 'Southern',
        'country_code' => 'SL',
    ]));

    expect($formatted)->toBe("Bojon Street\nBo\nSouthern\nSierra Leone");
});

it('ships 16 districts under provinces with parent links', function (): void {
    $areas = app(SierraLeoneGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(16)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('sl:district:bo')->name)->toBe('Bo')
        ->and($byId->get('sl:district:western-urban')->name)->toBe('Western Urban')
        ->and($byId->get('sl:district:kono')->name)->toBe('Kono');
});
