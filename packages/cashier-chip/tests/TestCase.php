<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Tests;

use AIArmada\CashierChip\CashierChipServiceProvider;
use AIArmada\CommerceSupport\SupportServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            SupportServiceProvider::class,
            CashierChipServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        Config::set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        Config::set('app.env', 'testing');
        Config::set('database.default', 'testing');
        Config::set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        Config::set('cache.default', 'array');
        Config::set('session.driver', 'array');
        Config::set('cashier-chip.features.owner.enabled', false);
        Config::set('cashier-chip.database.json_column_type', 'json');
        Config::set('cashier-chip.currency', 'MYR');
        Config::set('cashier-chip.currency_locale', 'ms_MY');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(realpath(__DIR__ . '/../database/migrations'));
    }
}
