<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedAddressStatesAction;
use AIArmada\Addressing\Models\State;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    $this->action = app(SeedAddressStatesAction::class);
});

it('seeds states with codes', function (): void {
    $this->action->execute([
        ['name' => 'Alpha', 'state_code' => 'AA', 'country_code' => 'US'],
        ['name' => 'Beta', 'state_code' => 'BB', 'country_code' => 'US'],
    ]);

    expect(State::count())->toBe(2)
        ->and(State::where('code', 'AA')->exists())->toBeTrue();
});

it('seeds codeless state with null code', function (): void {
    $this->action->execute([
        ['name' => 'Codeless', 'country_code' => 'US'],
    ]);

    $state = State::where('name', 'Codeless')->firstOrFail();

    expect($state->code)->toBeNull();
});

it('matches codeless state by name on re-seed', function (): void {
    $this->action->execute([
        ['name' => 'Codeless', 'country_code' => 'US'],
    ]);

    $second = $this->action->execute([
        ['name' => 'Codeless', 'country_code' => 'US'],
    ]);

    expect(State::where('name', 'Codeless')->count())->toBe(1)
        ->and($second['created'])->toBe(0)
        ->and($second['skipped'])->toBe(1);
});

it('is idempotent', function (): void {
    $rows = [
        ['name' => 'Alpha', 'state_code' => 'AA', 'country_code' => 'US'],
        ['name' => 'Beta', 'state_code' => 'BB', 'country_code' => 'US'],
    ];

    $first = $this->action->execute($rows);
    $second = $this->action->execute($rows);

    expect($second['created'])->toBe(0)
        ->and($second['updated'])->toBe(0)
        ->and($second['skipped'])->toBe($first['created']);
});
