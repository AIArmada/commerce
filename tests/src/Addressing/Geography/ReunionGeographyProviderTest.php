<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Reunion\ReunionAddressFormatter;
use AIArmada\Addressing\Geography\Reunion\ReunionGeographyProvider;

it('formats Reunionese addresses with the code left of the locality', function (): void {
    $formatted = app(ReunionAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de Paris',
        'city' => 'SAINT-DENIS',
        'postcode' => '97400',
        'country_code' => 'RE',
    ]));

    expect($formatted)->toBe("Rue de Paris\n97400 SAINT-DENIS\nReunion");
});

it('formats Saint-Pierre addresses with their own code', function (): void {
    $formatted = app(ReunionAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 300',
        'city' => 'SAINT-PIERRE',
        'postcode' => '97410',
        'country_code' => 'RE',
    ]));

    expect($formatted)->toBe("BP 300\n97410 SAINT-PIERRE\nReunion");
});

it('ships 24 communes under districts with parent links', function (): void {
    $areas = app(ReunionGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'commune');

    expect($l2)->toHaveCount(24)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('re:commune:cilaos')->name)->toBe('Cilaos')
        ->and($byId->get('re:commune:bras-panon')->name)->toBe('Bras-Panon')
        ->and($byId->get('re:commune:saint-denis')->name)->toBe('Saint-Denis');
});
