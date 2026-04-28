<?php

declare(strict_types=1);

it('configures uuid morph key type by default', function (): void {
    $repoRoot = dirname(__DIR__, 3);

    $configPath = $repoRoot . '/packages/commerce-support/config/commerce-support.php';
    $providerPath = $repoRoot . '/packages/commerce-support/src/SupportServiceProvider.php';

    $config = file_get_contents($configPath);
    $provider = file_get_contents($providerPath);

    expect($config)->toBeString();
    expect($config)->toContain("'database'");
    expect($config)->toContain("'morph_key_type'");
    expect($config)->toContain('COMMERCE_MORPH_KEY_TYPE');

    expect($provider)->toBeString();
    expect($provider)->toContain('commerce-support.database.morph_key_type');
    expect($provider)->toContain('Schema::defaultMorphKeyType');
});
