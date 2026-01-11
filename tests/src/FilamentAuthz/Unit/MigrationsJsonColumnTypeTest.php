<?php

declare(strict_types=1);

it('does not hardcode JSON columns in authz migrations', function (): void {
    $repoRoot = dirname(__DIR__, 4);

    $paths = [
        $repoRoot . '/packages/filament-authz/database/migrations/2024_12_09_000001_create_authz_enterprise_tables.php',
        $repoRoot . '/packages/filament-authz/database/migrations/2024_12_09_000002_create_authz_visual_tools_tables.php',
    ];

    foreach ($paths as $path) {
        expect($path)->toBeFile();

        $content = file_get_contents($path);

        expect($content)
            ->toBeString()
            ->not->toContain("->json('");
    }
});
