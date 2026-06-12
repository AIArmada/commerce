<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\FilamentAddressing\Resources\AddressResource;

it('address resource is disabled by default', function (): void {
    expect(config('filament-addressing.resources.addresses.enabled'))->toBeFalse();
});

it('address resource is not read-only by default config', function (): void {
    expect(AddressResource::isReadOnly())->toBeFalse();
});

it('address resource has correct model', function (): void {
    expect(AddressResource::getModel())->toBe(Address::class);
});

it('address export is disabled by default', function (): void {
    expect(config('filament-addressing.features.address_export'))->toBeFalse();
});
