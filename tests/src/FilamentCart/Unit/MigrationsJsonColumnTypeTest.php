<?php

declare(strict_types=1);

it('does not hardcode JSON columns in snapshot migrations', function (): void {
    $repoRoot = dirname(__DIR__, 4);

    $paths = [
        $repoRoot . '/packages/filament-cart/database/migrations/2000_08_01_000001_create_cart_snapshots_table.php',
        $repoRoot . '/packages/filament-cart/database/migrations/2000_08_01_000002_create_cart_snapshots_items_table.php',
        $repoRoot . '/packages/filament-cart/database/migrations/2000_08_01_000003_create_cart_snapshots_conditions_table.php',
    ];

    foreach ($paths as $path) {
        expect($path)->toBeFile();

        $content = file_get_contents($path);

        expect($content)
            ->toBeString()
            ->not->toContain("->json('");
    }
});
