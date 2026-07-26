<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Geography\Malaysia\MalaysiaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('preserves globally seeded states and adds missing Malaysian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '14',
        'name' => 'Kuala Lumpur',
        'label' => 'Kuala Lumpur',
    ]);

    app(MalaysiaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('WP Kuala Lumpur')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(16)
        ->and(State::query()->where('country_id', $country->id)->where('code', '16')->exists())->toBeTrue();
});

it('provides all sixteen Malaysian state and federal territory mappings', function (): void {
    $mappings = app(MalaysiaGeographyProvider::class)->stateAreaMappings();
    $mappingCodes = array_map(
        static fn (string | int $code): string => mb_str_pad((string) $code, 2, '0', STR_PAD_LEFT),
        array_keys($mappings),
    );

    expect($mappings)->toHaveCount(16)
        ->and($mappingCodes)->toBe([
            '01', '02', '03', '04', '05', '06', '07', '08',
            '09', '10', '11', '12', '13', '14', '15', '16',
        ]);
});

it('defines separate postal and administrative hierarchies with a shared first-level region', function (): void {
    $hierarchies = app(MalaysiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(2)
        ->and($hierarchies[0]->key)->toBe('postal')
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->label)->toBe('State / Federal Territory')
        ->and($hierarchies[0]->levels[1]->key)->toBe('locality')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('region')
        ->and($hierarchies[1]->key)->toBe('administrative')
        ->and($hierarchies[1]->levels[0]->key)->toBe('region')
        ->and($hierarchies[1]->levels[1]->key)->toBe('district')
        ->and($hierarchies[1]->levels[1]->parentKey)->toBe('region')
        ->and($hierarchies[1]->levels[2]->key)->toBe('subdivision')
        ->and($hierarchies[1]->levels[2]->parentKey)->toBe('district');
});
