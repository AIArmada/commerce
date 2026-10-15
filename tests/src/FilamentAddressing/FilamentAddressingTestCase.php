<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAddressing;

use AIArmada\Commerce\Tests\Fixtures\FilamentAddressingTestPanelProvider;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Commerce\Tests\TestPanelProvider;
use Filament\Notifications\NotificationsServiceProvider;

class FilamentAddressingTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('filament-addressing.resources.addresses.enabled', true);
        $app['config']->set('filament-addressing.resources.snapshots.enabled', true);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...array_map(
                fn (string $provider): string => $provider === TestPanelProvider::class
                ? FilamentAddressingTestPanelProvider::class
                : $provider,
                parent::getPackageProviders($app),
            ),
            NotificationsServiceProvider::class,
        ];
    }
}
