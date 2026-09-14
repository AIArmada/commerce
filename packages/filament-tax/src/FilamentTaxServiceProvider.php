<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax;

use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentTaxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-tax')
            ->hasConfigFile()
            ->hasViews('filament-tax')
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        Gate::policy(TaxZone::class, Policies\TaxZonePolicy::class);
        Gate::policy(TaxRate::class, Policies\TaxRatePolicy::class);
        Gate::policy(TaxClass::class, Policies\TaxClassPolicy::class);
        Gate::policy(TaxExemption::class, Policies\TaxExemptionPolicy::class);
    }
}
