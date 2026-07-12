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

it('falls back to COMMERCE_JSON_COLUMN_TYPE for commerce support', function (): void {
    unsetEnvVar('COMMERCE_SUPPORT_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/commerce-support/config/commerce-support.php');

    expect($config['database']['json_column_type'] ?? 'jsonb')->toBe('jsonb');
});

it('resolves json column type for commerce support via env fallback', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('COMMERCE_SUPPORT_JSON_COLUMN_TYPE=jsonb');

    expect(commerce_json_column_type('commerce-support', 'json'))->toBe('jsonb');
});

it('falls back to COMMERCE_JSON_COLUMN_TYPE for products', function (): void {
    unsetEnvVar('PRODUCTS_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/products/config/products.php');

    expect($config['database']['json_column_type'] ?? 'jsonb')->toBe('jsonb');
});

it('allows per-package override for products', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('PRODUCTS_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/products/config/products.php');

    expect($config['database']['json_column_type'] ?? 'jsonb')->toBe('jsonb');
});

it('falls back to COMMERCE_JSON_COLUMN_TYPE for customers', function (): void {
    unsetEnvVar('CUSTOMERS_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/customers/config/customers.php');

    expect($config['database']['json_column_type'] ?? 'jsonb')->toBe('jsonb');
});

it('allows per-package override for customers', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('CUSTOMERS_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/customers/config/customers.php');

    expect($config['database']['json_column_type'] ?? 'jsonb')->toBe('jsonb');
});

it('falls back to COMMERCE_JSON_COLUMN_TYPE for tax', function (): void {
    unsetEnvVar('TAX_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/tax/config/tax.php');

    expect($config['database']['json_column_type'] ?? 'jsonb')->toBe('jsonb');
});

it('allows per-package override for tax', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('TAX_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/tax/config/tax.php');

    expect($config['database']['json_column_type'] ?? 'jsonb')->toBe('jsonb');
});

it('falls back to COMMERCE_JSON_COLUMN_TYPE for filament cart', function (): void {
    unsetEnvVar('FILAMENT_CART_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/filament-cart/config/filament-cart.php');

    expect($config['database']['json_column_type'] ?? 'jsonb')->toBe('jsonb');
});

it('allows per-package override for filament cart', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('FILAMENT_CART_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/filament-cart/config/filament-cart.php');

    expect($config['database']['json_column_type'] ?? 'jsonb')->toBe('jsonb');
});

it('falls back to COMMERCE_JSON_COLUMN_TYPE for promotions', function (): void {
    unsetEnvVar('PROMOTIONS_JSON_COLUMN_TYPE');
    putenv('COMMERCE_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/promotions/config/promotions.php');

    expect($config['database']['json_column_type'] ?? 'jsonb')->toBe('jsonb');
});

it('allows per-package override for promotions', function (): void {
    putenv('COMMERCE_JSON_COLUMN_TYPE=json');
    putenv('PROMOTIONS_JSON_COLUMN_TYPE=jsonb');

    $config = require repoPath('packages/promotions/config/promotions.php');

    expect($config['database']['json_column_type'] ?? 'jsonb')->toBe('jsonb');
});

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

it('uses the configured json column type in the commerce support webhook migration', function (): void {
    $migration = file_get_contents(repoPath('packages/commerce-support/database/migrations/1970_01_01_000004_create_webhook_calls_table.php.stub'));

    expect($migration)
        ->toBeString()
        ->toContain("commerce_json_column_type('commerce-support', 'jsonb')")
        ->toContain("\$table->{\$jsonType}('headers')")
        ->toContain("\$table->{\$jsonType}('payload')")
        ->not->toContain("->json('headers')")
        ->not->toContain("->json('payload')");
});

it('uses the configured json column type in the membership applications migration', function (): void {
    $migration = file_get_contents(repoPath('packages/membership/database/migrations/2000_01_01_000001_create_membership_applications_table.php'));

    expect($migration)
        ->toBeString()
        ->toContain("commerce_json_column_type('membership', 'jsonb')")
        ->toContain("\$table->{\$jsonType}('meta')")
        ->not->toContain("->jsonb('meta')");
});

it('uses configured membership table names in both migrations', function (): void {
    $applications = file_get_contents(repoPath('packages/membership/database/migrations/2000_01_01_000001_create_membership_applications_table.php'));
    $invitations = file_get_contents(repoPath('packages/membership/database/migrations/2000_01_01_000002_create_membership_invitations_table.php'));

    expect($applications)
        ->toBeString()
        ->toContain("config('membership.database.tables.applications', 'membership_applications')");

    expect($invitations)
        ->toBeString()
        ->toContain("config('membership.database.tables.invitations', 'membership_invitations')");
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
