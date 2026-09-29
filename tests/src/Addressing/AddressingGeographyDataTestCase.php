<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Addressing;

use AIArmada\Addressing\AddressingServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Boot for geography tests that never touch the database.
 *
 * Formatting an address, reading area type labels, and resolving an area name
 * are all pure logic over the provider's own JSON/CSV data. AddressingGeographyTestCase
 * runs RefreshDatabase and 16 migrations per test, which is wasted work for
 * those assertions, and there are hundreds of them. This case boots the same
 * providers with no schema, so a dataset row costs a container resolve instead
 * of a database rebuild.
 *
 * Anything that reads or writes persisted rows stays on AddressingGeographyTestCase.
 */
abstract class AddressingGeographyDataTestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            DatabaseServiceProvider::class,
            EventServiceProvider::class,
            AddressingServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
