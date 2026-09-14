<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Csuite;

use AIArmada\Commerce\Tests\TestCase;
use Filament\Contracts\Plugin;
use Illuminate\Support\Str;

uses(TestCase::class);

it('resolves every bundled package and boots its declared providers', function (): void {
    $repoRoot = dirname(__DIR__, 3);

    $manifestPath = $repoRoot . '/packages/csuite/composer.json';
    expect($manifestPath)->toBeFile('csuite metapackage manifest is missing');

    /** @var array{type: string, require: array<string, string>} $manifest */
    $manifest = json_decode(
        (string) file_get_contents($manifestPath),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($manifest['type'])->toBe('metapackage');

    foreach ($manifest['require'] as $package => $constraint) {
        if (! str_starts_with($package, 'aiarmada/')) {
            continue;
        }

        expect($constraint)->toBe('self.version');

        $packageManifestPath = $repoRoot . '/packages/' . Str::after($package, 'aiarmada/') . '/composer.json';
        expect($packageManifestPath)->toBeFile("manifest for bundled package [{$package}] is missing");

        $packageManifest = json_decode(
            (string) file_get_contents($packageManifestPath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        expect($packageManifest['name'])->toBe($package);

        foreach ((array) data_get($packageManifest, 'extra.laravel.providers', []) as $provider) {
            expect(class_exists($provider))->toBeTrue();

            app()->register($provider);
            expect(app()->getProvider($provider))->not->toBeNull();
        }
    }

    $plugins = [
        'AIArmada\\FilamentAuthz\\FilamentAuthzPlugin',
        'AIArmada\\FilamentCart\\FilamentCartPlugin',
        'AIArmada\\FilamentCashier\\FilamentCashierPlugin',
        'AIArmada\\FilamentCashierChip\\FilamentCashierChipPlugin',
        'AIArmada\\FilamentChip\\FilamentChipPlugin',
        'AIArmada\\FilamentDocs\\FilamentDocsPlugin',
        'AIArmada\\FilamentInventory\\FilamentInventoryPlugin',
        'AIArmada\\FilamentJnt\\FilamentJntPlugin',
        'AIArmada\\FilamentVouchers\\FilamentVouchersPlugin',
    ];

    foreach ($plugins as $plugin) {
        expect(class_exists($plugin))->toBeTrue();
        expect(is_a($plugin, Plugin::class, true))->toBeTrue();
    }
});
