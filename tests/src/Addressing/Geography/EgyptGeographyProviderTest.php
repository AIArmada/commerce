<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Egypt\EgyptAddressFormatter;
use AIArmada\Addressing\Geography\Egypt\EgyptGeographyProvider;

it('formats Egyptian addresses with locality, province and postcode lines', function (): void {
    $formatted = app(EgyptAddressFormatter::class)->format(AddressData::from([
        'line1' => '30 Moussa Galal street',
        'city' => 'Al-Mohandessine',
        'state' => 'Giza',
        'postcode' => '3759914',
        'country_code' => 'EG',
    ]));

    expect($formatted)->toBe("30 Moussa Galal street\nAl-Mohandessine\nGiza\n3759914\nEgypt");
});

it('ships 365 COD-AB districts under governorates with parent links', function (): void {
    $areas = app(EgyptGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(365)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'eg:governorate:cairo'))->toHaveCount(42)
        ->and($l2->where('parentSourceId', 'eg:governorate:suez'))->toHaveCount(6)
        ->and($l2->where('parentSourceId', 'eg:governorate:luxor'))->toHaveCount(4)
        ->and($byId->get('eg:district:suez')->code)->toBe('EG0401')
        ->and($byId->get('eg:district:luxor:luxor')->parentSourceId)->toBe('eg:governorate:luxor');
});
