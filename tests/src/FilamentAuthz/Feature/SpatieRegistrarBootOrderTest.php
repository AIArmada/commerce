<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Gate;
use Spatie\Permission\PermissionRegistrar;

it('keeps Spatie PermissionRegistrar resolvable when Gate is resolved early', function (): void {
    // Simulate an app where something resolves Gate before Spatie\Permission\PermissionServiceProvider boots.
    // In that scenario, Spatie's callAfterResolving(Gate::class, ...) callback can fire immediately.
    app()->make(Gate::class);

    expect(app()->bound(PermissionRegistrar::class))->toBeTrue();

    // This should not throw.
    expect(app()->make(PermissionRegistrar::class))->toBeInstanceOf(PermissionRegistrar::class);
});
