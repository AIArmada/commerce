<?php

declare(strict_types=1);

namespace App\Providers;

use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryBackorder;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryCostLayer;
use AIArmada\Inventory\Models\InventoryDemandHistory;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\Models\InventorySerialHistory;
use AIArmada\Inventory\Models\InventoryStandardCost;
use AIArmada\Inventory\Models\InventorySupplierLeadtime;
use AIArmada\Inventory\Models\InventoryValuationSnapshot;
use AIArmada\Orders\Models\Order;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use AIArmada\Customers\Models\Customer;
use App\Listeners\HandleChipPaymentSuccess;
use App\Models\User;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        config()->set('commerce-support.owner.team_type', User::class);
        config()->set('pricing.features.owner.enabled', true);
        config()->set('tax.features.owner.enabled', true);

        $this->app->bind(OwnerResolverInterface::class, function (): OwnerResolverInterface {
            return new class implements OwnerResolverInterface
            {
                public function resolve(): ?Model
                {
                    $user = Auth::user();

                    if ($user instanceof Model) {
                        return $user;
                    }

                    $ownerFromQuery = request()->query('owner');

                    if (is_string($ownerFromQuery) && $ownerFromQuery !== '') {
                        session(['demo_owner_id' => $ownerFromQuery]);
                    }

                    $demoOwnerId = session('demo_owner_id');

                    if (is_string($demoOwnerId) && $demoOwnerId !== '') {
                        return User::query()->find($demoOwnerId);
                    }

                    return User::query()->first();
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'order' => Order::class,
            'product' => Product::class,
            'category' => Category::class,
            'user' => User::class,
            'customer' => Customer::class,
            'inventory_allocation' => InventoryAllocation::class,
            'inventory_backorder' => InventoryBackorder::class,
            'inventory_batch' => InventoryBatch::class,
            'inventory_cost_layer' => InventoryCostLayer::class,
            'inventory_demand_history' => InventoryDemandHistory::class,
            'inventory_level' => InventoryLevel::class,
            'inventory_location' => InventoryLocation::class,
            'inventory_movement' => InventoryMovement::class,
            'inventory_reorder_suggestion' => InventoryReorderSuggestion::class,
            'inventory_serial' => InventorySerial::class,
            'inventory_serial_history' => InventorySerialHistory::class,
            'inventory_standard_cost' => InventoryStandardCost::class,
            'inventory_supplier_leadtime' => InventorySupplierLeadtime::class,
            'inventory_valuation_snapshot' => InventoryValuationSnapshot::class,
            'permission' => Permission::class,
            'role' => Role::class,
        ]);

        // Register CHIP webhook listeners for order processing
        Event::listen(PurchasePaid::class, HandleChipPaymentSuccess::class);

        FilamentTimezone::set('Asia/Kuala_Lumpur');

    }
}
