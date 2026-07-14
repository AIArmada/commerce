<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SaveAddressAreaAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    $this->action = app(SaveAddressAreaAction::class);
});

it('saves a top-level area with derived fields', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    $area = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
    ]);

    expect($area->country_code)->toBe('MY')
        ->and($area->level)->toBe(1)
        ->and($area->slug)->toBe('selangor')
        ->and($area->source)->toBe('manual')
        ->and($area->source_id)->toStartWith('my-state-selangor-');
});

it('derives child level and parent source id', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
        'source_id' => 'state-selangor',
    ]);

    $district = $this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $state->id,
        'type' => 'district',
        'name' => 'Petaling',
    ]);

    expect($district->level)->toBe(2)
        ->and($district->parent_id)->toBe($state->id)
        ->and($district->parent_source_id)->toBe('state-selangor');
});

it('rejects a parent from another country', function (): void {
    $malaysia = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $otherCountry = AddressCountry::query()->create([
        'iso2' => 'ZZ',
        'name' => 'Testland',
    ]);
    $parent = $this->action->handle([
        'country_id' => $otherCountry->id,
        'type' => 'state',
        'name' => 'Central',
    ]);

    expect(fn (): AddressArea => $this->action->handle([
        'country_id' => $malaysia->id,
        'parent_id' => $parent->id,
        'type' => 'district',
        'name' => 'Petaling',
    ]))->toThrow(ValidationException::class);
});

it('rejects hierarchy cycles when updating an area', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = $this->action->handle([
        'country_id' => $country->id,
        'type' => 'state',
        'name' => 'Selangor',
    ]);
    $district = $this->action->handle([
        'country_id' => $country->id,
        'parent_id' => $state->id,
        'type' => 'district',
        'name' => 'Petaling',
    ]);

    expect(fn (): AddressArea => $this->action->handle([
        'parent_id' => $district->id,
    ], $state))->toThrow(ValidationException::class);
});
