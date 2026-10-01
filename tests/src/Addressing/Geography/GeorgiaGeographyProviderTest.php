<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SearchAddressAreasAction;
use AIArmada\Addressing\Geography\Georgia\GeorgiaGeographyProvider;
use AIArmada\Addressing\Models\AddressAreaRole;
use AIArmada\Addressing\Support\CountryAddressProfileResolver;

it('ships four self-governing cities as level-2 areas under regions', function (): void {
    $areas = app(GeorgiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $cities = $areas->where('type', 'city');

    expect($cities->pluck('sourceId')->sort()->values()->all())->toBe([
        'ge:city:batumi',
        'ge:city:kutaisi',
        'ge:city:poti',
        'ge:city:rustavi',
        'ge:city:tbilisi',
    ])
        ->and($byId->get('ge:city:tbilisi')->level)->toBe(1)
        ->and($byId->get('ge:city:tbilisi')->parentSourceId)->toBeNull()
        ->and($cities->where('level', 2)->count())->toBe(4)
        ->and($byId->get('ge:city:batumi')->parentSourceId)->toBe('ge:autonomous_republic:adjara');
});

it('seeds consistently and resolves level-2 cities through the municipality role', function (): void {
    expect($this->seedProviderConsistently(GeorgiaGeographyProvider::class))->toBe([]);

    // 65 municipalities + 16 districts + 4 self-governing cities.
    expect(AddressAreaRole::query()->where('role', 'municipality')->count())->toBe(85);

    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->definitionForRole('GE', 'municipality'))->not->toBeNull();

    $search = app(SearchAddressAreasAction::class);

    expect($search->execute(query: 'Batumi', countryCode: 'GE', role: 'municipality')->pluck('name')->all())
        ->toContain('Batumi')
        ->and($search->execute(query: 'Gagra', countryCode: 'GE', role: 'municipality')->pluck('name')->all())
        ->toContain('Gagra')
        ->and($search->execute(query: 'Keda', countryCode: 'GE', role: 'municipality')->pluck('name')->all())
        ->toContain('Keda');
});

it('keeps Tbilisi on the city role instead of the municipality role', function (): void {
    expect($this->seedProviderConsistently(GeorgiaGeographyProvider::class))->toBe([]);

    $search = app(SearchAddressAreasAction::class);

    expect($search->execute(query: 'Tbilisi', countryCode: 'GE', role: 'city')->pluck('name')->all())
        ->toContain('Tbilisi')
        ->and($search->execute(query: 'Tbilisi', countryCode: 'GE', role: 'municipality')->pluck('name')->all())
        ->not->toContain('Tbilisi');
});
