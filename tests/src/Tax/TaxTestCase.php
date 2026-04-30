<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Tax;

use AIArmada\Commerce\Tests\TestCase as BaseTestCase;
use AIArmada\Tax\TaxServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;

abstract class TaxTestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            TaxServiceProvider::class,
            LaravelSettingsServiceProvider::class,
        ]);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Configure tax settings for testing
        $app['config']->set('tax.features.enabled', true);
        $app['config']->set('tax.defaults.prices_include_tax', false);
        $app['config']->set('tax.defaults.round_at_subtotal', true);
        $app['config']->set('tax.defaults.calculate_tax_on_shipping', true);
        $app['config']->set('tax.features.zone_resolution.use_customer_address', true);
        $app['config']->set('tax.features.zone_resolution.address_priority', 'shipping');
        $app['config']->set('tax.features.zone_resolution.unknown_zone_behavior', 'zero');
        $app['config']->set('tax.features.exemptions.enabled', true);
        $app['config']->set('tax.features.owner.enabled', false);
        // Keep owner auto-assignment aligned with package defaults for owner-scope tests
        $app['config']->set('tax.features.owner.auto_assign_on_create', true);

        // Configure tax table names
        $app['config']->set('tax.database.tables.tax_zones', 'tax_zones');
        $app['config']->set('tax.database.tables.tax_classes', 'tax_classes');
        $app['config']->set('tax.database.tables.tax_rates', 'tax_rates');
        $app['config']->set('tax.database.tables.tax_exemptions', 'tax_exemptions');

        // Configure Spatie Laravel Settings
        $app['config']->set('settings.repositories.database', [
            'type' => 'database',
            'model' => 'Spatie\\LaravelSettings\\Models\\SettingsProperty',
            'table' => 'settings',
            'connection' => 'testing',
        ]);
        $app['config']->set('settings.default_repository', 'database');
        $app['config']->set('settings.repositories', [
            'database' => [
                'type' => 'database',
                'model' => 'Spatie\\LaravelSettings\\Models\\SettingsProperty',
                'table' => 'settings',
                'connection' => 'testing',
            ],
        ]);
    }
}
