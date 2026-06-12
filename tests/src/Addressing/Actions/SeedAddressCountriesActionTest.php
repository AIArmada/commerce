<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Models\AddressCountry;

beforeEach(function (): void {
    $this->action = app(SeedAddressCountriesAction::class);
});

it('seeds bundled countries', function (): void {
    $result = $this->action->execute();

    expect($result['created'])->toBeGreaterThan(200);
    expect($result['updated'])->toBe(0);
    expect($result['skipped'])->toBe(0);
});

it('is idempotent', function (): void {
    $first = $this->action->execute();
    $second = $this->action->execute();

    expect($second['created'])->toBe(0);
    expect($second['updated'])->toBe(0);
    expect($second['skipped'])->toBe($first['created']);
});

it('includes MY Malaysia with ISO2 MY and ISO3 MYS', function (): void {
    $this->action->execute();

    $my = AddressCountry::where('iso2', 'MY')->first();

    expect($my)->not->toBeNull();
    expect($my->iso3)->toBe('MYS');
    expect($my->name)->toContain('Malaysia');
});

it('stores calling_codes as array', function (): void {
    $this->action->execute();

    $my = AddressCountry::where('iso2', 'MY')->first();

    expect($my->calling_codes)->toBeArray();
});

it('stores currency_codes as array', function (): void {
    $this->action->execute();

    $my = AddressCountry::where('iso2', 'MY')->first();

    expect($my->currency_codes)->toBeArray();
});

it('stores timezones as array', function (): void {
    $this->action->execute();

    $my = AddressCountry::where('iso2', 'MY')->first();

    expect($my->timezones)->toBeArray();
});
