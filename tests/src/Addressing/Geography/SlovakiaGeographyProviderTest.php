<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Slovakia\SlovakiaAddressFormatter;
use AIArmada\Addressing\Geography\Slovakia\SlovakiaGeographyProvider;

it('formats Slovak addresses with the spaced postcode left of the office', function (): void {
    $formatted = app(SlovakiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lúčna 1157/13',
        'city' => 'TRNAVA',
        'postcode' => '917 01',
        'country_code' => 'SK',
    ]));

    expect($formatted)->toBe("Lúčna 1157/13\n917 01 TRNAVA\nSlovakia");
});
it('formats Slovak Žilina addresses with the town postcode', function (): void {
    $formatted = app(SlovakiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Národná 5',
        'city' => 'Žilina',
        'postcode' => '010 01',
        'country_code' => 'SK',
    ]));

    expect($formatted)->toBe("Národná 5\n010 01 Žilina\nSlovakia");
});

it('ships 79 districts under regions with parent links', function (): void {
    $areas = app(SlovakiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(79)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('sk:district:bratislava-i')->name)->toBe('Bratislava I')
        ->and($byId->get('sk:district:kosice-iii')->name)->toBe('Košice III')
        ->and($byId->get('sk:district:bardejov')->name)->toBe('Bardejov');
});
