<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SearchAddressAreasAction;
use AIArmada\Addressing\Geography\Lithuania\LithuaniaGeographyProvider;
use AIArmada\Addressing\Models\AddressAreaRole;
use AIArmada\Addressing\Support\CountryAddressProfileResolver;

it('types the seven city municipalities with miestas names', function (): void {
    $areas = app(LithuaniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $cities = $areas->where('type', 'city_municipality')->keyBy->code;

    expect($cities)->toHaveCount(7)
        ->and($cities->get('02')->name)->toBe('Alytaus miestas')
        ->and($cities->get('15')->name)->toBe('Kauno miestas')
        ->and($cities->get('20')->name)->toBe('Klaipėdos miestas')
        ->and($cities->get('31')->name)->toBe('Palangos miestas')
        ->and($cities->get('32')->name)->toBe('Panevėžio miestas')
        ->and($cities->get('43')->name)->toBe('Šiaulių miestas')
        ->and($cities->get('57')->name)->toBe('Vilniaus miestas');

    $byId = $areas->keyBy->sourceId;

    expect($byId->get('lt:district_municipality:klaipeda')->code)->toBe('21')
        ->and($byId->get('lt:district_municipality:panevezys')->code)->toBe('33');
});

it('ships 60 municipalities under counties with parent links', function (): void {
    $areas = app(LithuaniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['district_municipality', 'municipality', 'city_municipality']);

    expect($l2)->toHaveCount(60)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('lt:district_municipality:alytus-03')->parentSourceId)->toBe('lt:county:alytus')
        ->and($byId->get('lt:municipality:marijampole')->type)->toBe('municipality')
        ->and($byId->get('lt:municipality:marijampole')->parentSourceId)->toBe('lt:county:marijampole');
});

it('seeds consistently and resolves every municipal type through the municipality role', function (): void {
    expect($this->seedProviderConsistently(LithuaniaGeographyProvider::class))->toBe([]);

    expect(AddressAreaRole::query()->where('role', 'municipality')->count())->toBe(60)
        ->and(AddressAreaRole::query()->whereIn('role', ['district_municipality', 'city_municipality'])->exists())->toBeFalse();

    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->definitionForRole('LT', 'municipality'))->not->toBeNull();

    $search = app(SearchAddressAreasAction::class);

    expect($search->execute(query: 'Vilniaus miestas', countryCode: 'LT', role: 'municipality')->pluck('name')->all())
        ->toContain('Vilniaus miestas')
        ->and($search->execute(query: 'Akmenė', countryCode: 'LT', role: 'municipality')->pluck('name')->all())
        ->toContain('Akmenė')
        ->and($search->execute(query: 'Marijampolė', countryCode: 'LT', role: 'municipality')->pluck('name')->all())
        ->toContain('Marijampolė');
});
