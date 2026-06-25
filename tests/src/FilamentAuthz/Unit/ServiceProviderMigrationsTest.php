<?php

declare(strict_types=1);

it('registers the authz package migration files', function (): void {
    $migratorPaths = collect(app('migrator')->paths());

    expect($migratorPaths->contains(static function (string $path): bool {
        $normalizedPath = str_replace('\\', '/', $path);

        return str_contains($normalizedPath, 'packages/authz/')
            && str_contains($normalizedPath, 'database/migrations');
    }))->toBeTrue();
});

it('uses the configured Authz scope table in its migration', function (): void {
    $migration = file_get_contents(dirname(__DIR__, 4) . '/packages/authz/database/migrations/2000_01_01_000001_create_authz_scopes_table.php');

    expect($migration)
        ->toBeString()
        ->toContain("authz_table('scopes')");
});
