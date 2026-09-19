<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Russia\RussiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(RussiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('subject')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Russian tree with state links', function (): void {
    $this->seedCountry('RU');

    $result = app(SeedCountryGeographiesAction::class)->execute('RU');
    $country = AddressCountry::query()->where('iso2', 'RU')->firstOrFail();

    expect($result['seeded'])->toContain('RU')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(83)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(83)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'republic')->count())->toBe(21)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'krai')->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'oblast')->count())->toBe(46)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'okrug')->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'federal_city')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_oblast')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(83);
});
