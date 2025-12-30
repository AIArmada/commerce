<?php

declare(strict_types=1);

namespace AIArmada\Customers;

use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerNote;
use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Models\Wishlist;
use AIArmada\Customers\Models\WishlistItem;
use AIArmada\Customers\Policies\AddressPolicy;
use AIArmada\Customers\Policies\CustomerNotePolicy;
use AIArmada\Customers\Policies\CustomerPolicy;
use AIArmada\Customers\Policies\SegmentPolicy;
use AIArmada\Customers\Policies\WishlistItemPolicy;
use AIArmada\Customers\Policies\WishlistPolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CustomersServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('customers')
            ->hasConfigFile()
            ->hasTranslations()
            ->discoversMigrations();
    }

    public function bootingPackage(): void
    {
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Segment::class, SegmentPolicy::class);
        Gate::policy(Address::class, AddressPolicy::class);
        Gate::policy(CustomerNote::class, CustomerNotePolicy::class);
        Gate::policy(Wishlist::class, WishlistPolicy::class);
        Gate::policy(WishlistItem::class, WishlistItemPolicy::class);
    }
}
