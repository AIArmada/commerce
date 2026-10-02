<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAffiliateNetwork;

use AIArmada\Commerce\Tests\AffiliateNetwork\AffiliateNetworkTestCase;
use AIArmada\Commerce\Tests\FilamentAffiliateNetwork\Fixtures\AffiliateNetworkTestPanelProvider;
use AIArmada\FilamentAffiliateNetwork\FilamentAffiliateNetworkServiceProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Livewire\LivewireServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;

abstract class FilamentAffiliateNetworkTestCase extends AffiliateNetworkTestCase
{
    protected function getPackageProviders($app): array
    {
        $providers = parent::getPackageProviders($app);
        $providers[] = BladeIconsServiceProvider::class;
        $providers[] = BladeHeroiconsServiceProvider::class;
        $providers[] = SupportServiceProvider::class;
        $providers[] = ActionsServiceProvider::class;
        $providers[] = SchemasServiceProvider::class;
        $providers[] = FormsServiceProvider::class;
        $providers[] = TablesServiceProvider::class;
        $providers[] = InfolistsServiceProvider::class;
        $providers[] = FilamentServiceProvider::class;
        $providers[] = LivewireServiceProvider::class;
        $providers[] = LaravelSettingsServiceProvider::class;
        $providers[] = FilamentAffiliateNetworkServiceProvider::class;
        $providers[] = AffiliateNetworkTestPanelProvider::class;

        return $providers;
    }
}
