<?php

declare(strict_types=1);

it('writes only environment variables recognized by package configuration', function (): void {
    $repoRoot = dirname(__DIR__, 3);

    $commandPath = $repoRoot . '/packages/commerce-support/src/Commands/SetupCommand.php';
    expect($commandPath)->toBeFile('setup wizard source is missing');

    $commandSource = (string) file_get_contents($commandPath);

    preg_match_all("/\\\$updates\\['([A-Z0-9_]+)'\\]/", $commandSource, $matches);

    /** @var array<int, string> $written */
    $written = array_values(array_unique($matches[1]));

    expect($written)->not->toBeEmpty();

    $configSources = '';
    foreach (glob($repoRoot . '/packages/*/config/*.php') ?: [] as $configPath) {
        $configSources .= (string) file_get_contents($configPath);
    }
    $configSources .= (string) file_get_contents($repoRoot . '/packages/commerce-support/src/helpers.php');

    $unrecognized = array_values(array_filter(
        $written,
        fn (string $key): bool => ! str_contains($configSources, $key)
    ));

    expect($unrecognized)->toBe([]);
});

it('no longer writes retired chip and jnt variables', function (): void {
    $repoRoot = dirname(__DIR__, 3);

    $commandSource = (string) file_get_contents(
        $repoRoot . '/packages/commerce-support/src/Commands/SetupCommand.php'
    );

    $retiredKeys = ['CHIP_BRAND_ID', 'CHIP_SECRET_KEY', 'CHIP_MODE', 'CHIP_WEBHOOK_URL', 'JNT_API_KEY', 'JNT_API_URL'];

    $stillReferenced = array_values(array_filter(
        $retiredKeys,
        fn (string $key): bool => str_contains($commandSource, "'{$key}'")
    ));

    expect($stillReferenced)->toBe([]);
});
