<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Contracts\MutableStepRegistryInterface;
use AIArmada\Checkout\Contracts\ScopedCapacityCheckoutProviderInterface;
use AIArmada\Checkout\Steps\ReserveScopedCapacityStep;
use AIArmada\Checkout\Steps\CommitScopedCapacityStep;
use AIArmada\Checkout\Integrations\InventoryAdapter;
use AIArmada\Checkout\Integrations\PromotionsAdapter;
use AIArmada\Checkout\Integrations\TaxAdapter;
use AIArmada\Checkout\Integrations\VouchersAdapter;
use AIArmada\Checkout\Steps\ApplyDiscountsStep;
use AIArmada\Checkout\Steps\CalculateTaxStep;
use AIArmada\Checkout\Steps\ReserveInventoryStep;
use AIArmada\Inventory\InventoryServiceProvider;
use AIArmada\Promotions\PromotionsServiceProvider;
use AIArmada\Tax\TaxServiceProvider;
use AIArmada\Vouchers\VoucherServiceProvider;

final class RegisterCheckoutOptionalSteps
{
    public function register(MutableStepRegistryInterface $registry): void
    {
        $this->registerInventoryStep($registry);
        $this->registerScopedCapacitySteps($registry);
        $this->registerTaxStep($registry);
        $this->registerDiscountStep($registry);
    }

    private function registerInventoryStep(MutableStepRegistryInterface $registry): void
    {
        if ($this->hasInventoryPackage() && config('checkout.integrations.inventory.enabled', true)) {
            app()->singleton(InventoryAdapter::class);
            $registry->register('reserve_inventory', new ReserveInventoryStep(
                inventoryAdapter: app(InventoryAdapter::class),
                stepRegistry: $registry,
            ));
        } else {
            $registry->disable('reserve_inventory');
        }
    }

    private function registerScopedCapacitySteps(MutableStepRegistryInterface $registry): void
    {
        if (! $this->hasInventoryPackage() || ! app()->bound(ScopedCapacityCheckoutProviderInterface::class)) {
            $registry->disable('reserve_scoped_capacity');
            $registry->disable('commit_scoped_capacity');

            return;
        }

        $registry->registerLazy('reserve_scoped_capacity', fn () => app(ReserveScopedCapacityStep::class));
        $registry->registerLazy('commit_scoped_capacity', fn () => app(CommitScopedCapacityStep::class));
    }

    private function registerTaxStep(MutableStepRegistryInterface $registry): void
    {
        if ($this->hasTaxPackage() && config('checkout.integrations.tax.enabled', true)) {
            $registry->register('calculate_tax', new CalculateTaxStep(
                taxAdapter: app(TaxAdapter::class),
            ));
        } else {
            $registry->disable('calculate_tax');
        }
    }

    private function registerDiscountStep(MutableStepRegistryInterface $registry): void
    {
        if ($this->hasDiscountPackages() && $this->isDiscountsEnabled()) {
            $registry->register('apply_discounts', new ApplyDiscountsStep(
                promotionsAdapter: app(PromotionsAdapter::class),
                vouchersAdapter: app(VouchersAdapter::class),
            ));
        } else {
            $registry->disable('apply_discounts');
        }
    }

    private function hasInventoryPackage(): bool
    {
        return class_exists(InventoryServiceProvider::class);
    }

    private function hasTaxPackage(): bool
    {
        return class_exists(TaxServiceProvider::class);
    }

    private function hasDiscountPackages(): bool
    {
        return class_exists(PromotionsServiceProvider::class)
            || class_exists(VoucherServiceProvider::class);
    }

    private function isDiscountsEnabled(): bool
    {
        return config('checkout.integrations.promotions.enabled', true)
            || config('checkout.integrations.vouchers.enabled', true);
    }
}
