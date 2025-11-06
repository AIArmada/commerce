<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

it('discovers all core package configs for publishing', function (): void {
    $expectedTags = [
        'cart-config',
        'chip-config',
        'stock-config',
        'vouchers-config',
        'jnt-config',
        'docs-config',
    ];

    foreach ($expectedTags as $tag) {
        // Get publishable items for this tag
        $publishables = Artisan::call('vendor:publish', [
            '--tag' => $tag,
            '--no-interaction' => true,
        ]);

        expect($publishables)->toBe(0)
            ->and(File::exists(config_path(str_replace('-config', '', $tag).'.php')))
            ->toBeTrue("Config file for {$tag} should exist after package discovery");
    }
})->group('package', 'discovery');

it('discovers all filament plugin configs for publishing', function (): void {
    $expectedTags = [
        'filament-cart-config',
        'filament-chip-config',
        'filament-vouchers-config',
    ];

    foreach ($expectedTags as $tag) {
        // Just verify the tag is recognized and publishable (exit code 0)
        $result = Artisan::call('vendor:publish', [
            '--tag' => $tag,
            '--no-interaction' => true,
        ]);

        expect($result)->toBe(0, "Tag {$tag} should be discoverable and publishable");
    }
})->group('package', 'discovery', 'filament');

it('discovers and runs all package migrations', function (): void {
    // Verify that migrations from all packages have been discovered and run
    // by checking that their core tables exist in the database
    $expectedTables = [
        'cart' => ['carts', 'cart_snapshots'],
        'stock' => ['stock_transactions'],
        'vouchers' => ['vouchers', 'voucher_usage'],
        'chip' => ['chip_purchases', 'chip_payments'],
        'jnt' => ['jnt_orders'],
        'docs' => ['docs'],
    ];

    foreach ($expectedTables as $package => $tables) {
        foreach ($tables as $table) {
            expect(Schema::hasTable($table))->toBeTrue(
                "Package {$package} migration should have created table '{$table}'"
            );
        }
    }
})->group('package', 'discovery', 'migrations');
