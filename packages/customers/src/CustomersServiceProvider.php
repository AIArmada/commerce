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
use Illuminate\Support\ServiceProvider;

class CustomersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/customers.php', 'customers');
    }

    public function boot(): void
    {
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Segment::class, SegmentPolicy::class);
        Gate::policy(Address::class, AddressPolicy::class);
        Gate::policy(CustomerNote::class, CustomerNotePolicy::class);
        Gate::policy(Wishlist::class, WishlistPolicy::class);
        Gate::policy(WishlistItem::class, WishlistItemPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/customers.php' => config_path('customers.php'),
            ], 'customers-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'customers-migrations');

            if (! $this->app->runningUnitTests()) {
                $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
            }
        }

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'customers');
    }
}
