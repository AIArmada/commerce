<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\SearchAddressAreasAction;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\ResolutionGap;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;

beforeEach(function (): void {
    $this->seedCountry('MY');

    app(ImportAddressAreasAction::class)->execute(new ArrayAddressAreaSource('areas', [
        new AddressAreaData(source: 'areas', sourceId: 'kl', countryCode: 'MY', type: 'wilayah_persekutuan', level: 1, name: 'Wilayah Persekutuan Kuala Lumpur'),
        new AddressAreaData(source: 'areas', sourceId: 'wangsa', countryCode: 'MY', parentSourceId: 'kl', type: 'locality', level: 2, name: 'Wangsa Maju'),
    ]));

    $this->areas = AddressArea::query()->where('source', 'areas')->get()->keyBy('source_id');
});

function searchGapCount(): int
{
    return ResolutionGap::query()->where('source', 'search')->count();
}

it('logs an unfiltered miss as a search gap', function (): void {
    $results = app(SearchAddressAreasAction::class)->execute('Xyzzy No Such Place', countryCode: 'MY', role: 'postal_locality');

    expect($results)->toBeEmpty();

    $gap = ResolutionGap::query()->where('source', 'search')->firstOrFail();

    expect($gap->country_code)->toBe('MY')
        ->and($gap->role)->toBe('postal_locality')
        ->and($gap->value)->toBe('Xyzzy No Such Place')
        ->and($gap->reason)->toBe('unmatched')
        ->and($gap->hits)->toBe(1);
});

it('defaults the search gap role to area', function (): void {
    app(SearchAddressAreasAction::class)->execute('Xyzzy No Such Place', countryCode: 'MY');

    expect(ResolutionGap::query()->where('source', 'search')->firstOrFail()->role)->toBe('area');
});

it('bumps hits when the same miss repeats', function (): void {
    $search = app(SearchAddressAreasAction::class);

    $search->execute('Xyzzy No Such Place', countryCode: 'MY');
    $search->execute('Xyzzy No Such Place', countryCode: 'MY');

    expect(searchGapCount())->toBe(1)
        ->and(ResolutionGap::query()->where('source', 'search')->firstOrFail()->hits)->toBe(2);
});

it('logs nothing on a hit', function (): void {
    $results = app(SearchAddressAreasAction::class)->execute('Wangsa Maju', countryCode: 'MY');

    expect($results)->not->toBeEmpty()
        ->and(searchGapCount())->toBe(0);
});

it('logs nothing without a country', function (): void {
    app(SearchAddressAreasAction::class)->execute('Xyzzy No Such Place');

    expect(searchGapCount())->toBe(0);
});

it('logs nothing for short queries', function (): void {
    app(SearchAddressAreasAction::class)->execute('Xy', countryCode: 'MY');

    expect(searchGapCount())->toBe(0);
});

it('logs nothing for unloggable telemetry input', function (): void {
    $search = app(SearchAddressAreasAction::class);

    $search->execute(str_repeat('Xyzzy ', 50), countryCode: 'MY');
    $search->execute('Xyzzy No Such Place', countryCode: 'MY', role: str_repeat('r', 51));

    expect(searchGapCount())->toBe(0);
});

it('logs nothing for filter-scoped misses', function (): void {
    $search = app(SearchAddressAreasAction::class);
    $wangsaId = (string) $this->areas['wangsa']->getKey();

    $search->execute('Xyzzy No Such Place', countryCode: 'MY', type: 'locality');
    $search->execute('Xyzzy No Such Place', countryCode: 'MY', parentId: $wangsaId);
    $search->execute('Xyzzy No Such Place', countryCode: 'MY', hierarchyType: 'postal');
    $search->execute('Xyzzy No Such Place', countryCode: 'MY', postalCode: '99999');

    expect(searchGapCount())->toBe(0);
});
