<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kiribati\KiribatiAddressFormatter;
use AIArmada\Addressing\Geography\Kiribati\KiribatiGeographyProvider;

it('formats Kiribati addresses with the code right of the island', function (): void {
    $formatted = app(KiribatiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 487',
        'line2' => 'Betio',
        'city' => 'Sth Tarawa',
        'postcode' => 'KI0108',
        'country_code' => 'KI',
    ]));

    expect($formatted)->toBe("PO Box 487\nBetio\nSth Tarawa KI0108\nKiribati");
});
it('formats Line Islands addresses with their own code', function (): void {
    $formatted = app(KiribatiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 3',
        'city' => 'Kiritimati',
        'postcode' => 'KI0303',
        'country_code' => 'KI',
    ]));

    expect($formatted)->toBe("PO Box 3\nKiritimati KI0303\nKiribati");
});

it('ships 24 councils under island groups with parent links', function (): void {
    $areas = app(KiribatiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(24)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'ki:island:gilbert'))->toHaveCount(20)
        ->and($l2->where('parentSourceId', 'ki:island:line'))->toHaveCount(3)
        ->and($byId->get('ki:council:canton')->parentSourceId)->toBe('ki:island:phoenix')
        ->and($byId->get('ki:council:south-tarawa')->parentSourceId)->toBe('ki:island:gilbert');
});
