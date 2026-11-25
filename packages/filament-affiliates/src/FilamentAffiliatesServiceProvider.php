<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates;

use AIArmada\Affiliates\Support\Integrations\CartBridge;
use AIArmada\Affiliates\Support\Integrations\VoucherBridge;
use AIArmada\FilamentAffiliates\Platform\ExtensionRegistry;
use AIArmada\FilamentAffiliates\Platform\FeatureCatalog;
use AIArmada\FilamentAffiliates\Platform\FeatureModule;
use Filament\Facades\Filament;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentAffiliatesServiceProvider extends PackageServiceProvider
{
    /** @var array{features: array<string, string>, owners: array<string, string>, writes: array<string, list<string>>, extensions: array<string, list<string>>}|null */
    private ?array $catalog = null;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-affiliates')
            ->hasConfigFile('filament-affiliates')
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $catalog = $this->catalog();
        $this->app->singleton(ExtensionRegistry::class, fn (Application $app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $name) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $name . '\\FeatureModule');
            if ($module instanceof FeatureModule) {
                $module->register($this->app);
            }
        }

        $this->app->singleton(FilamentAffiliatesPlugin::class);
        $this->app->singleton(VoucherBridge::class);

        $this->registerSettingsMigrationPath();
    }

    private function registerSettingsMigrationPath(): void
    {
        $packagePath = __DIR__ . '/../database/settings';

        if (! is_dir($packagePath)) {
            return;
        }

        $paths = config('settings.migrations_paths', []);

        if (! in_array($packagePath, $paths, true)) {
            $paths[] = $packagePath;

            config(['settings.migrations_paths' => $paths]);
        }
    }

    public function packageBooted(): void
    {
        foreach (array_keys($this->catalog()['features']) as $name) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $name . '\\FeatureModule');
            if ($module instanceof FeatureModule) {
                $module->boot($this->app);
            }
        }

        Filament::serving(function (): void {
            if (config('filament-affiliates.integrations.filament_cart', true)) {
                app(CartBridge::class)->warm();
            }

            if (config('filament-affiliates.integrations.filament_vouchers', true)) {
                app(VoucherBridge::class)->warm();
            }
        });

    }

    /** @return array{features: array<string, string>, owners: array<string, string>, writes: array<string, list<string>>, extensions: array<string, list<string>>} */
    private function catalog(): array
    {
        return $this->catalog ??= FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
    }
}
