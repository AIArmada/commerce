<?php

declare(strict_types=1);

namespace AIArmada\Customers;

use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentSubjectResolverInterface;
use AIArmada\Customers\Features\Identity\EntryPoints\Policies\CustomerNotePolicy;
use AIArmada\Customers\Features\Identity\Models\CustomerNote;
use AIArmada\Customers\Features\Identity\Services\CustomersPaymentSubjectDriver;
use AIArmada\Customers\Features\Segmentation\EntryPoints\Commands\RebuildSegmentsCommand;
use AIArmada\Customers\Features\Segmentation\EntryPoints\Policies\CustomerGroupPolicy;
use AIArmada\Customers\Features\Segmentation\EntryPoints\Policies\SegmentPolicy;
use AIArmada\Customers\Features\Segmentation\Models\CustomerGroup;
use AIArmada\Customers\Features\Segmentation\Models\Segment;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Platform\ExtensionRegistry;
use AIArmada\Customers\Platform\FeatureCatalog;
use AIArmada\Customers\Platform\FeatureModule;
use AIArmada\Customers\Policies\AddressPolicy;
use AIArmada\Customers\Policies\CustomerPolicy;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CustomersServiceProvider extends PackageServiceProvider
{
    /** @var list<FeatureModule> */
    private array $modules = [];

    public function packageRegistered(): void
    {
        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn ($app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $feature) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule');

            if (! $module instanceof FeatureModule) {
                throw new InvalidArgumentException('Invalid customers feature module: ' . $feature);
            }

            $module->register($this->app);
            $this->modules[] = $module;
        }

        $this->app->booted(function (): void {
            $this->app->make(PaymentSubjectResolverInterface::class)
                ->register($this->app->make(CustomersPaymentSubjectDriver::class));
        });
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('customers')
            ->hasConfigFile('customers')
            ->runsMigrations()
            ->discoversMigrations()
            ->hasTranslations()
            ->hasCommand(RebuildSegmentsCommand::class);
    }

    public function packageBooted(): void
    {
        foreach ($this->modules as $module) {
            $module->boot($this->app);
        }

        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Segment::class, SegmentPolicy::class);
        Gate::policy(Address::class, AddressPolicy::class);
        Gate::policy(CustomerNote::class, CustomerNotePolicy::class);
        Gate::policy(CustomerGroup::class, CustomerGroupPolicy::class);
    }
}
