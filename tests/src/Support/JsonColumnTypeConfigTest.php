<?php

declare(strict_types=1);

afterEach(function (): void {
    unsetEnvVar('COMMERCE_JSON_COLUMN_TYPE');

    unsetEnvVar('AFFILIATE_NETWORK_JSON_COLUMN_TYPE');
    unsetEnvVar('CHECKOUT_JSON_COLUMN_TYPE');
    unsetEnvVar('PRODUCTS_JSON_COLUMN_TYPE');
    unsetEnvVar('CUSTOMERS_JSON_COLUMN_TYPE');
    unsetEnvVar('TAX_JSON_COLUMN_TYPE');
    unsetEnvVar('FILAMENT_CART_JSON_COLUMN_TYPE');
    unsetEnvVar('CASHIER_CHIP_JSON_COLUMN_TYPE');
    unsetEnvVar('PROMOTIONS_JSON_COLUMN_TYPE');
});

it('resolves per-package override for hyphenated package keys', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('AFFILIATE_NETWORK_JSON_COLUMN_TYPE=jsonb');

    expect(commerce_json_column_type('affiliate-network', 'json'))->toBe('jsonb');
});

it('resolves underscore env keys for helper lookups consistently', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('CASHIER_CHIP_JSON_COLUMN_TYPE=jsonb');

    expect(commerce_json_column_type('cashier-chip', 'json'))->toBe('jsonb');
});

it('falls back to COMMERCE_JSON_COLUMN_TYPE for products', function (): void {
    unsetEnvVar('PRODUCTS_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/products/config/products.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('allows per-package override for products', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('PRODUCTS_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/products/config/products.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('falls back to COMMERCE_JSON_COLUMN_TYPE for customers', function (): void {
    unsetEnvVar('CUSTOMERS_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/customers/config/customers.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('allows per-package override for customers', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('CUSTOMERS_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/customers/config/customers.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('falls back to COMMERCE_JSON_COLUMN_TYPE for tax', function (): void {
    unsetEnvVar('TAX_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/tax/config/tax.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('allows per-package override for tax', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('TAX_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/tax/config/tax.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('falls back to COMMERCE_JSON_COLUMN_TYPE for filament cart', function (): void {
    unsetEnvVar('FILAMENT_CART_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/filament-cart/config/filament-cart.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('allows per-package override for filament cart', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('FILAMENT_CART_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/filament-cart/config/filament-cart.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('falls back to COMMERCE_JSON_COLUMN_TYPE for promotions', function (): void {
    unsetEnvVar('PROMOTIONS_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/promotions/config/promotions.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('allows per-package override for promotions', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('PROMOTIONS_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/promotions/config/promotions.php');

    expect($config['database']['json_column_type'])->toBe('jsonb');
});

it('uses COMMERCE_JSON_COLUMN_TYPE fallback for every package config that defines database.json_column_type', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    foreach (glob(repoPath('packages/*/config/*.php')) ?: [] as $configPath) {
        $config = require $configPath;

        if (! isset($config['database']) || ! is_array($config['database']) || ! array_key_exists('json_column_type', $config['database'])) {
            continue;
        }

        $packageName = basename(dirname(dirname((string) $configPath)));
        $packageEnvPrefix = mb_strtoupper((string) str_replace('-', '_', $packageName));

        unsetEnvVar($packageEnvPrefix . '_JSON_COLUMN_TYPE');

        expect($config['database']['json_column_type'])
            ->toBe('jsonb', sprintf('Expected %s config to honor COMMERCE_JSON_COLUMN_TYPE fallback', $packageName));
    }
});

function unsetEnvVar(string $key): void
{
    putenv($key);

    unset($_ENV[$key], $_SERVER[$key]);
}

function repoPath(string $relativePath): string
{
    return dirname(__DIR__, 3) . '/' . $relativePath;
}
