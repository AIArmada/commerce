<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\AddressSnapshot;

it('snapshot resource is disabled by default', function (): void {
    expect(config('filament-addressing.resources.snapshots.enabled'))->toBeFalse();
});

it('snapshot resource is read-only by default', function (): void {
    expect(config('filament-addressing.resources.snapshots.read_only'))->toBeTrue();
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
