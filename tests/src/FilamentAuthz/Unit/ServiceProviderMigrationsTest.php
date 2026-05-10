<?php

declare(strict_types=1);

it('registers the authz package migration files', function (): void {
    $migratorPaths = collect(app('migrator')->paths());

    expect($migratorPaths->contains(static function (string $path): bool {
        $normalizedPath = str_replace('\\', '/', $path);

        return str_contains($normalizedPath, 'packages/filament-authz/')
            && str_contains($normalizedPath, 'database/migrations');
    }))->toBeTrue();
});
