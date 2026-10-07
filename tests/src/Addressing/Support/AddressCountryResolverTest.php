<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountryReferencesAction;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\AddressCountryResolver;

beforeEach(function (): void {
    $this->seedCountry('MY');
    app(SeedAddressCountryReferencesAction::class)->execute();
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

    expect($this->resolver->timezoneFor($country->id))->toBe('Asia/Kuala_Lumpur')
        ->and($this->resolver->resolve(null))->toBeNull()
        ->and($this->resolver->resolve('Neverland'))->toBeNull();
});

it('resolves a country by name, case-insensitively', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();

    expect($this->resolver->resolve('Malaysia')?->id)->toBe($country->id)
        ->and($this->resolver->resolve('malaysia')?->id)->toBe($country->id);
});
