<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guam\GuamAddressFormatter;
use AIArmada\Addressing\Geography\Guam\GuamGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GuamGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('village')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Guamanian tree with state links', function (): void {
    $this->seedCountry('GU');

    $result = app(SeedCountryGeographiesAction::class)->execute('GU');
    $country = AddressCountry::query()->where('iso2', 'GU')->firstOrFail();

    expect($result['seeded'])->toContain('GU')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(19)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(19)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'village')->count())->toBe(19)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(19);
});

it('formats Guamanian addresses with the US ZIP layout', function (): void {
    $formatted = app(GuamAddressFormatter::class)->format(AddressData::from([
        'line1' => '489 ARMY DR',
        'city' => 'BARRIGADA',
        'postcode' => '96913-9998',
        'country_code' => 'GU',
    ]));

    expect($formatted)->toBe("489 ARMY DR\nBARRIGADA GU 96913-9998\nGuam");
});
it('formats Hagatna addresses with its own ZIP', function (): void {
    $formatted = app(GuamAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Hagatna',
        'postcode' => '96910',
        'country_code' => 'GU',
    ]));

    expect($formatted)->toBe("PO Box 1\nHagatna GU 96910\nGuam");
});
