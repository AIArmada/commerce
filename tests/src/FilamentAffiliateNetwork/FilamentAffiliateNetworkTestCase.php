<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAffiliateNetwork;

use AIArmada\Commerce\Tests\AffiliateNetwork\AffiliateNetworkTestCase;
use AIArmada\Commerce\Tests\FilamentAffiliateNetwork\Fixtures\AffiliateNetworkTestPanelProvider;
use AIArmada\FilamentAffiliateNetwork\FilamentAffiliateNetworkServiceProvider;
use Filament\FilamentServiceProvider;
use Livewire\LivewireServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;

abstract class FilamentAffiliateNetworkTestCase extends AffiliateNetworkTestCase
{
    protected function getPackageProviders($app): array
    {
        $providers = parent::getPackageProviders($app);
        $providers[] = FilamentServiceProvider::class;
        $providers[] = LivewireServiceProvider::class;
        $providers[] = LaravelSettingsServiceProvider::class;
        $providers[] = FilamentAffiliateNetworkServiceProvider::class;
        $providers[] = AffiliateNetworkTestPanelProvider::class;

        return $providers;
    }
}
