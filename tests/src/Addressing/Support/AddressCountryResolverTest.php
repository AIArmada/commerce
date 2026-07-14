<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\AddressCountryResolver;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    $this->resolver = app(AddressCountryResolver::class);
});

it('resolves a country by model, uuid, and iso2', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    expect($this->resolver->resolve($country)->is($country))->toBeTrue()
        ->and($this->resolver->resolveId($country->id))->toBe($country->id)
        ->and($this->resolver->resolve('my')?->id)->toBe($country->id);
});

it('resolves a country timezone and rejects unsupported input', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    expect($this->resolver->timezoneFor($country->id))->toBe('+08:00')
        ->and($this->resolver->resolve('Malaysia'))->toBeNull()
        ->and($this->resolver->resolve(null))->toBeNull();
});
