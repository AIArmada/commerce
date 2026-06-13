<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\AddressSnapshot;
use AIArmada\FilamentAddressing\Resources\AddressSnapshotResource;

it('snapshot resource is disabled by default', function (): void {
    expect(config('filament-addressing.resources.snapshots.enabled'))->toBeFalse();
});

it('snapshot resource is read-only by default', function (): void {
    expect(config('filament-addressing.resources.snapshots.read_only'))->toBeTrue();
});

it('snapshot resource navigation sort is configured', function (): void {
    expect(AddressSnapshotResource::getNavigationSort())->toBe(83);
});

it('snapshot resource navigation icon follows config', function (): void {
    $original = config('filament-addressing.navigation.icons.snapshots');

    config()->set('filament-addressing.navigation.icons.snapshots', 'heroicon-o-folder');

    try {
        expect(AddressSnapshotResource::getNavigationIcon())->toBe('heroicon-o-folder');
    } finally {
        config()->set('filament-addressing.navigation.icons.snapshots', $original);
    }
});

it('snapshot resource has correct model', function (): void {
    expect(config('filament-addressing.resources.snapshots.model'))->toBe(AddressSnapshot::class);
});

it('show provider payload is disabled by default', function (): void {
    expect(config('filament-addressing.features.show_provider_payload'))->toBeFalse();
});

it('show source payload is disabled by default', function (): void {
    expect(config('filament-addressing.features.show_source_payload'))->toBeFalse();
});
