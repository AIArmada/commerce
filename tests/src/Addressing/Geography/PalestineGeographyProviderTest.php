<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Palestine\PalestineAddressFormatter;
use AIArmada\Addressing\Geography\Palestine\PalestineGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(PalestineGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('governorate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Palestinian tree with state links', function (): void {
    $this->seedCountry('PS');

    $result = app(SeedCountryGeographiesAction::class)->execute('PS');
    $country = AddressCountry::query()->where('iso2', 'PS')->firstOrFail();

    expect($result['seeded'])->toContain('PS')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'governorate')->count())->toBe(16)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(16);
});

it('formats Palestinian addresses with the P postcode right of the locality', function (): void {
    $formatted = app(PalestineAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Irsal Street 10',
        'city' => 'RAMALLAH AND AL-BIREH',
        'postcode' => 'P6100154',
        'country_code' => 'PS',
    ]));

    expect($formatted)->toBe("Irsal Street 10\nRAMALLAH AND AL-BIREH P6100154\nPalestine");
});
it('formats Palestinian addresses passing short P codes through', function (): void {
    $formatted = app(PalestineAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Dahiyat al Bareed',
        'city' => 'JERUSALEM',
        'postcode' => 'P126',
        'country_code' => 'PS',
    ]));

    expect($formatted)->toBe("Dahiyat al Bareed\nJERUSALEM P126\nPalestine");
});
