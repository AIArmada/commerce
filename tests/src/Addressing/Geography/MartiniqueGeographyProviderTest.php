<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Martinique\MartiniqueAddressFormatter;
use AIArmada\Addressing\Geography\Martinique\MartiniqueGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MartiniqueGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Martinican tree with state links', function (): void {
    $this->seedCountry('MQ');

    $result = app(SeedCountryGeographiesAction::class)->execute('MQ');
    $country = AddressCountry::query()->where('iso2', 'MQ')->firstOrFail();

    expect($result['seeded'])->toContain('MQ')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(4)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(4);
});

it('formats Martinican addresses with the postcode left of the locality', function (): void {
    $formatted = app(MartiniqueAddressFormatter::class)->format(AddressData::from([
        'line1' => '25 RUE CARNOT',
        'city' => 'LA TRINITE',
        'postcode' => '97220',
        'country_code' => 'MQ',
    ]));

    expect($formatted)->toBe("25 RUE CARNOT\n97220 LA TRINITE\nMartinique");
});
it('formats Martinican Fort-de-France addresses with the town postcode', function (): void {
    $formatted = app(MartiniqueAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Liberté 9',
        'city' => 'FORT-DE-FRANCE',
        'postcode' => '97200',
        'country_code' => 'MQ',
    ]));

    expect($formatted)->toBe("Rue de la Liberté 9\n97200 FORT-DE-FRANCE\nMartinique");
});
