<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCitiesAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedAddressStatesAction;
use AIArmada\Addressing\Models\City;
use AIArmada\Addressing\Models\State;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    $this->action = app(SeedAddressCitiesAction::class);
});

it('does not collapse same-name cities across different states', function (): void {
    app(SeedAddressStatesAction::class)->execute([
        ['name' => 'Alpha', 'code' => 'AA', 'country_code' => 'US'],
        ['name' => 'Beta', 'code' => 'BB', 'country_code' => 'US'],
    ]);

    $this->action->execute([
        ['name' => 'Springfield', 'country_code' => 'US', 'state_code' => 'AA'],
        ['name' => 'Springfield', 'country_code' => 'US', 'state_code' => 'BB'],
    ]);

    $cities = City::where('name', 'Springfield')->get();

    expect($cities)->toHaveCount(2)
        ->and($cities->pluck('state_id')->unique())->toHaveCount(2);
});

it('links cities to the correct state', function (): void {
    app(SeedAddressStatesAction::class)->execute([
        ['name' => 'Alpha', 'code' => 'AA', 'country_code' => 'US'],
    ]);

    $this->action->execute([
        ['name' => 'City X', 'country_code' => 'US', 'state_code' => 'AA'],
    ]);

    $state = State::where('code', 'AA')->firstOrFail();
    $city = City::where('name', 'City X')->firstOrFail();

    expect($city->state_id)->toBe($state->id);
});

it('stores city with null state when state_code is absent', function (): void {
    $this->action->execute([
        ['name' => 'No State City', 'country_code' => 'US'],
    ]);

    $city = City::where('name', 'No State City')->firstOrFail();

    expect($city->state_id)->toBeNull();
});

it('is idempotent', function (): void {
    app(SeedAddressStatesAction::class)->execute([
        ['name' => 'Alpha', 'code' => 'AA', 'country_code' => 'US'],
    ]);

    $rows = [
        ['name' => 'City X', 'country_code' => 'US', 'state_code' => 'AA'],
    ];

    $first = $this->action->execute($rows);
    $second = $this->action->execute($rows);

    expect($second['created'])->toBe(0)
        ->and($second['updated'])->toBe(0)
        ->and($second['skipped'])->toBe($first['created']);
});

it('updates existing city in place on re-seed', function (): void {
    app(SeedAddressStatesAction::class)->execute([
        ['name' => 'Alpha', 'code' => 'AA', 'country_code' => 'US'],
    ]);

    $this->action->execute([
        ['name' => 'City X', 'country_code' => 'US', 'state_code' => 'AA', 'latitude' => 1.0],
    ]);

    $this->action->execute([
        ['name' => 'City X', 'country_code' => 'US', 'state_code' => 'AA', 'latitude' => 2.0],
    ]);

    expect(City::where('name', 'City X')->count())->toBe(1);
    expect(City::where('name', 'City X')->firstOrFail()->latitude)->toBe(2.0);
});
