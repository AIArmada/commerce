<?php

declare(strict_types=1);

namespace App\Providers;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Cashier\Cashier;
use AIArmada\CashierChip\Cashier as CashierChip;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Models\Client;
use AIArmada\Chip\Models\Payment;
use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Customers\Models\Customer;
use AIArmada\Docs\Models\Doc;
use AIArmada\CommerceSupport\Models\Permission;
use AIArmada\CommerceSupport\Models\Role;
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
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\PriceTier;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use App\Checkout\DemoPaymentProcessor;
use App\Checkout\DemoRequestExperimentSubjectResolver;
use App\Listeners\HandleChipPaymentSuccess;
use App\Models\User;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        config()->set('cashier.default', 'chip');
        config()->set('cashier.models.billable', User::class);
        config()->set('checkout.owner.enabled', true);
        config()->set('checkout.payment.default_gateway', $this->defaultCheckoutGateway());
        config()->set('checkout.payment.gateway_priority', ['chip', 'demo', 'cashier-chip', 'cashier']);
        config()->set('checkout.redirects.success', '/order/{order_id}/success');
        config()->set('checkout.redirects.failure', '/checkout?checkout_result=failed&checkout_session_id={session_id}');
        config()->set('checkout.redirects.cancel', '/checkout?checkout_result=cancelled&checkout_session_id={session_id}');
        config()->set('customers.features.owner.enabled', true);
        config()->set('growth.features.owner.enabled', true);
        config()->set('growth.features.experiment_middleware.enabled', true);
        config()->set('growth.http.experiment_middleware.subject_resolver', DemoRequestExperimentSubjectResolver::class);
        config()->set('inventory.owner.enabled', true);
        config()->set('orders.owner.enabled', true);
        config()->set('products.features.owner.enabled', true);
        config()->set('signals.owner.enabled', true);
        config()->set('signals.integrations.browser.enabled', true);
        config()->set('signals.integrations.cart.enabled', true);
        config()->set('signals.integrations.filament_cart.enabled', true);
        config()->set('filament-growth.features.dashboard', false);
        config()->set('filament-signals.features.dashboard', false);
        config()->set('filament-cart.owner.enabled', true);
        config()->set('pricing.features.owner.enabled', true);
        config()->set('tax.features.owner.enabled', true);
        config()->set('vouchers.owner.enabled', true);
        config()->set('jnt.owner.enabled', true);
        config()->set('affiliates.owner.enabled', true);
        config()->set('filament-authz.owner.enabled', true);
        config()->set('docs.owner.enabled', true);

        // Demo-only: avoid requiring puppeteer (Browsershot) during simulated webhooks.
        config()->set('chip.integrations.docs.paid_doc_type', null);

        Cashier::useCustomerModel(User::class);
        CashierChip::useCustomerModel(User::class);

        $this->app->afterResolving(PaymentGatewayResolverInterface::class, function (PaymentGatewayResolverInterface $resolver): void {
            if (! $resolver->hasGateway('demo')) {
                $resolver->register('demo', $this->app->make(DemoPaymentProcessor::class));
            }
        });

        $this->app->bind(OwnerResolverInterface::class, function (): OwnerResolverInterface {
            return new class implements OwnerResolverInterface
            {
                /**
                 * Default to the seeded admin owner, while still allowing demo owner switching
                 * through the session-backed /demo/owner/{user} route.
                 */
                public function resolve(): ?Model
                {
                    $request = request();

                    if ($request->hasSession()) {
                        $ownerId = $request->session()->get('demo_owner_id');

                        if (is_string($ownerId) || is_int($ownerId)) {
                            $sessionOwner = User::query()->find((string) $ownerId);

                            if ($sessionOwner instanceof Model) {
                                return $sessionOwner;
                            }
                        }
                    }

                    $owner = User::query()
                        ->where('email', 'admin@commerce.demo')
                        ->first();

                    if ($owner instanceof Model) {
                        return $owner;
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
        Model::unguard();

        Relation::enforceMorphMap([
            'order' => Order::class,
            'chip_client' => Client::class,
            'chip_purchase' => Purchase::class,
            'chip_payment' => Payment::class,
            'price' => Price::class,
            'price_list' => PriceList::class,
            'price_tier' => PriceTier::class,
            'promotion' => Promotion::class,
            'affiliate' => Affiliate::class,
            'affiliate_fraud_signal' => AffiliateFraudSignal::class,
            'doc' => Doc::class,
            'product' => Product::class,
            'category' => Category::class,
            'user' => User::class,
            'customer' => Customer::class,
            'tax_zone' => TaxZone::class,
            'tax_rate' => TaxRate::class,
            'tax_class' => TaxClass::class,
            'tax_exemption' => TaxExemption::class,
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

    private function defaultCheckoutGateway(): string
    {
        $chipApiKey = config('chip.collect.api_key');
        $chipBrandId = config('chip.collect.brand_id');

        return is_string($chipApiKey) && $chipApiKey !== '' && is_string($chipBrandId) && $chipBrandId !== ''
            ? 'chip'
            : 'demo';
    }
}
