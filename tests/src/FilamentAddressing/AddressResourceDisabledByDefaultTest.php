<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\FilamentAddressing\Resources\AddressResource;
use AIArmada\FilamentAddressing\Resources\AddressResource\Pages\ListAddresses;
use Filament\Actions\ExportAction;

it('address resource is disabled by default', function (): void {
    expect(config('filament-addressing.resources.addresses.enabled'))->toBeFalse();
});

it('address resource is not read-only by default config', function (): void {
    expect(AddressResource::isReadOnly())->toBeFalse();
});

it('address resource navigation sort is configured', function (): void {
    expect(AddressResource::getNavigationSort())->toBe(82);
});

it('address resource navigation icon follows config', function (): void {
    $original = config('filament-addressing.navigation.icons.addresses');

    config()->set('filament-addressing.navigation.icons.addresses', 'heroicon-o-question-mark-circle');

    try {
        expect(AddressResource::getNavigationIcon())->toBe('heroicon-o-question-mark-circle');
    } finally {
        config()->set('filament-addressing.navigation.icons.addresses', $original);
    }
});

it('address resource has correct model', function (): void {
    expect(AddressResource::getModel())->toBe(Address::class);
});

it('address export is disabled by default', function (): void {
    expect(config('filament-addressing.features.address_export'))->toBeFalse();
});

it('address export action is toggled by config', function (): void {
    $original = config('filament-addressing.features.address_export', false);

    $page = new ListAddresses;
    $method = new ReflectionMethod($page, 'getHeaderActions');
    $method->setAccessible(true);

    config()->set('filament-addressing.features.address_export', false);

    try {
        $actions = $method->invoke($page);

        expect(collect($actions)->contains(fn ($action): bool => $action instanceof ExportAction))->toBeFalse();

        config()->set('filament-addressing.features.address_export', true);

        $actions = $method->invoke($page);

        expect(collect($actions)->contains(fn ($action): bool => $action instanceof ExportAction))->toBeTrue();
    } finally {
        config()->set('filament-addressing.features.address_export', $original);
    }
});
