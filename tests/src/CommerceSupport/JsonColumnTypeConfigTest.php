<?php

declare(strict_types=1);

afterEach(function (): void {
    unsetEnvVar('COMMERCE_JSON_COLUMN_TYPE');
    unsetEnvVar('COMMERCE_SUPPORT_JSON_COLUMN_TYPE');

    unsetEnvVar('AFFILIATE_NETWORK_JSON_COLUMN_TYPE');
    unsetEnvVar('CHECKOUT_JSON_COLUMN_TYPE');
    unsetEnvVar('PRODUCTS_JSON_COLUMN_TYPE');
    unsetEnvVar('CUSTOMERS_JSON_COLUMN_TYPE');
    unsetEnvVar('TAX_JSON_COLUMN_TYPE');
    unsetEnvVar('FILAMENT_CART_JSON_COLUMN_TYPE');
    unsetEnvVar('CASHIER_CHIP_JSON_COLUMN_TYPE');
    unsetEnvVar('PROMOTIONS_JSON_COLUMN_TYPE');
    unsetEnvVar('MEMBERSHIP_JSON_COLUMN_TYPE');
});

it('resolves per-package override via the helper', function (string $package, string $envKey): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv($envKey . '=jsonb');

    expect(commerce_json_column_type($package, 'json'))->toBe('jsonb');
})->with([
    'hyphenated package key' => ['affiliate-network', 'AFFILIATE_NETWORK_JSON_COLUMN_TYPE'],
    'underscore env key' => ['cashier-chip', 'CASHIER_CHIP_JSON_COLUMN_TYPE'],
    'commerce support' => ['commerce-support', 'COMMERCE_SUPPORT_JSON_COLUMN_TYPE'],
]);

it('falls back to COMMERCE_JSON_COLUMN_TYPE for package configs', function (string $envKey, string $configPath): void {
    unsetEnvVar($envKey);
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath($configPath);

    expect($config['database']['json_column_type'] ?? 'jsonb')->toBe('jsonb');
})->with([
    'commerce support' => ['COMMERCE_SUPPORT_JSON_COLUMN_TYPE', 'packages/commerce-support/config/commerce-support.php'],
    'products' => ['PRODUCTS_JSON_COLUMN_TYPE', 'packages/products/config/products.php'],
    'customers' => ['CUSTOMERS_JSON_COLUMN_TYPE', 'packages/customers/config/customers.php'],
    'tax' => ['TAX_JSON_COLUMN_TYPE', 'packages/tax/config/tax.php'],
    'filament cart' => ['FILAMENT_CART_JSON_COLUMN_TYPE', 'packages/filament-cart/config/filament-cart.php'],
    'promotions' => ['PROMOTIONS_JSON_COLUMN_TYPE', 'packages/promotions/config/promotions.php'],
]);

it('allows per-package override for package configs', function (string $envKey, string $configPath): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv($envKey . '=jsonb');

    $config = require repoPath($configPath);

    expect($config['database']['json_column_type'] ?? 'jsonb')->toBe('jsonb');
})->with([
    'products' => ['PRODUCTS_JSON_COLUMN_TYPE', 'packages/products/config/products.php'],
    'customers' => ['CUSTOMERS_JSON_COLUMN_TYPE', 'packages/customers/config/customers.php'],
    'tax' => ['TAX_JSON_COLUMN_TYPE', 'packages/tax/config/tax.php'],
    'filament cart' => ['FILAMENT_CART_JSON_COLUMN_TYPE', 'packages/filament-cart/config/filament-cart.php'],
    'promotions' => ['PROMOTIONS_JSON_COLUMN_TYPE', 'packages/promotions/config/promotions.php'],
]);

it('uses COMMERCE_JSON_COLUMN_TYPE fallback for every package', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $packages = [
        'affiliate-network', 'affiliates', 'addressing', 'authz', 'cart',
        'cashier', 'cashier-chip', 'checkout', 'chip', 'commerce-support',
        'communications', 'contacting', 'customers', 'docs', 'engagement',
        'events', 'feedback', 'growth', 'inventory', 'jnt', 'membership',
        'moderation', 'orders', 'pricing', 'products', 'promotions',
        'references', 'seating', 'shipping', 'signals', 'tax', 'ticketing',
        'vouchers',
        'filament-addressing', 'filament-affiliate-network',
        'filament-affiliates', 'filament-authz', 'filament-cart',
        'filament-cashier', 'filament-cashier-chip', 'filament-chip',
        'filament-commerce-support', 'filament-communications',
        'filament-contacting', 'filament-customers', 'filament-docs',
        'filament-engagement', 'filament-events', 'filament-feedback',
        'filament-growth', 'filament-inventory', 'filament-jnt',
        'filament-orders', 'filament-pricing', 'filament-products',
        'filament-promotions', 'filament-seating', 'filament-shipping',
        'filament-signals', 'filament-tax', 'filament-ticketing',
        'filament-vouchers',
    ];

    foreach ($packages as $pkg) {
        $packageEnvPrefix = mb_strtoupper(str_replace('-', '_', $pkg));
        unsetEnvVar($packageEnvPrefix . '_JSON_COLUMN_TYPE');

        expect(commerce_json_column_type($pkg))
            ->toBe('jsonb', sprintf('Expected %s to fall back to COMMERCE_JSON_COLUMN_TYPE', $pkg));
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
