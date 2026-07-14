<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\AddressAreaStateBridge;
use Illuminate\Support\Str;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
});

it('bridges a state to its level one area and back from a child area', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'name' => 'WP Kuala Lumpur',
        'label' => 'Wilayah Persekutuan Kuala Lumpur',
    ]);
    $area = AddressArea::query()->create([
        'country_id' => $country->id,
        'country_code' => 'MY',
        'type' => 'state',
        'level' => 1,
        'name' => 'Kuala Lumpur',
        'slug' => 'kuala-lumpur',
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);
    AddressAreaStateLink::query()->create([
        'address_area_id' => $area->id,
        'state_id' => $state->id,
    ]);
    $district = AddressArea::query()->create([
        'country_id' => $country->id,
        'parent_id' => $area->id,
        'country_code' => 'MY',
        'type' => 'district',
        'level' => 2,
        'name' => 'Central',
        'slug' => 'central',
        'source' => 'test',
        'source_id' => Str::uuid()->toString(),
    ]);

    expect(AddressAreaStateBridge::areaIdForState($state))->toBe($area->id)
        ->and(AddressAreaStateBridge::stateIdForArea($district))->toBe($state->id);
});
