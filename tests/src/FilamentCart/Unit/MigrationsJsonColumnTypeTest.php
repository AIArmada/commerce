<?php

declare(strict_types=1);

it('does not hardcode JSON columns in snapshot migrations', function (): void {
    $repoRoot = dirname(__DIR__, 4);

    $paths = [
        $repoRoot . '/packages/cart/database/migrations/2000_08_01_000003_create_cart_snapshots_table.php',
        $repoRoot . '/packages/cart/database/migrations/2000_08_01_000004_create_cart_snapshots_items_table.php',
        $repoRoot . '/packages/cart/database/migrations/2000_08_01_000005_create_cart_snapshots_conditions_table.php',
    ];

    foreach ($paths as $path) {
        expect($path)->toBeFile();

        $content = file_get_contents($path);

        expect($content)
            ->toBeString()
            ->not->toContain("->json('");
        expect($content)->toContain("commerce_json_column_type('cart'");
    }
});

it('guards jsonb GIN indexes behind PostgreSQL driver checks', function (): void {
    $repoRoot = dirname(__DIR__, 4);

    $paths = [
        $repoRoot . '/packages/cart/database/migrations/2000_08_01_000003_create_cart_snapshots_table.php',
        $repoRoot . '/packages/cart/database/migrations/2000_08_01_000004_create_cart_snapshots_items_table.php',
        $repoRoot . '/packages/cart/database/migrations/2000_08_01_000005_create_cart_snapshots_conditions_table.php',
    ];

    foreach ($paths as $path) {
        expect($path)->toBeFile();

        $content = file_get_contents($path);

        $hasPostgreSqlDriverGuard = str_contains((string) $content, "ConnectionDriver::name(Schema::getConnection()) === 'pgsql'")
            || str_contains((string) $content, "Schema::getConnection()->getDriverName() === 'pgsql'");

        expect($content)->toBeString();
        expect($hasPostgreSqlDriverGuard)->toBeTrue();
    }
});
