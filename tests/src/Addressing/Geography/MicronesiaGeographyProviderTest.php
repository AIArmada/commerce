<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Micronesia\MicronesiaAddressFormatter;
use AIArmada\Addressing\Geography\Micronesia\MicronesiaGeographyProvider;

it('formats Micronesian addresses with the US ZIP layout', function (): void {
    $formatted = app(MicronesiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 123',
        'city' => 'Pohnpei',
        'postcode' => '96941',
        'country_code' => 'FM',
    ]));

    expect($formatted)->toBe("PO Box 123\nPohnpei FM 96941\nMicronesia");
});
it('formats Chuuk addresses with its own ZIP', function (): void {
    $formatted = app(MicronesiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 9',
        'city' => 'Weno',
        'postcode' => '96942',
        'country_code' => 'FM',
    ]));

    expect($formatted)->toBe("PO Box 9\nWeno FM 96942\nMicronesia");
});

it('ships 75 municipalities and cities under states with parent links', function (): void {
    $areas = app(MicronesiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(75)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'fm:state:chuuk'))->toHaveCount(40)
        ->and($l2->where('parentSourceId', 'fm:state:kosrae'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'fm:state:pohnpei'))->toHaveCount(11)
        ->and($l2->where('parentSourceId', 'fm:state:yap'))->toHaveCount(20)
        ->and($l2->where('type', 'city')->count())->toBe(2)
        ->and($byId->get('fm:city:weno')->parentSourceId)->toBe('fm:state:chuuk')
        ->and($byId->get('fm:municipality:utwe')->name)->toBe('Utwe');
});
