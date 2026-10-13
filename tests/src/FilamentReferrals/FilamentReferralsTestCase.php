<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentReferrals;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestPanelProvider;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentReferrals\FilamentReferralsPlugin;
use AIArmada\FilamentReferrals\FilamentReferralsServiceProvider;
use Composer\Autoload\ClassLoader;
use Filament\Panel;
use Filament\Widgets\WidgetsServiceProvider;
use Tests\src\Referrals\ReferralsTestCase as ReferralsCoreTestCase;

$projectRoot = dirname(__DIR__, 3);
/** @var ClassLoader $composerLoader */
$composerLoader = require $projectRoot . '/vendor/autoload.php';
$composerLoader->addPsr4('AIArmada\\FilamentReferrals\\', $projectRoot . '/packages/filament-referrals/src');

final class FilamentReferralsTestPanelProvider extends TestPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return parent::panel($panel)->plugin(FilamentReferralsPlugin::make());
    }
}

abstract class FilamentReferralsTestCase extends ReferralsCoreTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders(mixed $app): array
    {
        $providers = array_values(array_filter(
            parent::getPackageProviders($app),
            static fn (string $provider): bool => $provider !== TestPanelProvider::class,
        ));

        return [
            ...$providers,
            WidgetsServiceProvider::class,
            FilamentReferralsServiceProvider::class,
            FilamentReferralsTestPanelProvider::class,
        ];
    }

    protected function setCurrentOwner(User $owner): void
    {
        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));
    }
}
