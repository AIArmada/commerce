<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Tests;

use AIArmada\Chip\ChipServiceProvider;
use AIArmada\CommerceSupport\SupportServiceProvider as CommerceSupportServiceProvider;
use AIArmada\FilamentChip\FilamentChipServiceProvider;
use AIArmada\FilamentChip\Tests\Fixtures\TestPanelProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Support\SupportServiceProvider as FilamentSupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

class TestCase extends Orchestra
{
    protected function defineEnvironment($app): void
    {
        // Setup the test environment
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'testing');
        $app['config']->set('database.default', 'testing');

        // Required CHIP config (validated at boot)
        $app['config']->set('chip.collect.api_key', 'test-api-key');
        $app['config']->set('chip.collect.brand_id', 'test-brand-id');

        // Use in-memory SQLite for testing
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure session
        $app['config']->set('session.driver', 'array');
    }

    protected function getPackageProviders($app): array
    {
        return [
            CommerceSupportServiceProvider::class,
            ChipServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            FilamentSupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentChipServiceProvider::class,
            TestPanelProvider::class,
        ];
    }
}
