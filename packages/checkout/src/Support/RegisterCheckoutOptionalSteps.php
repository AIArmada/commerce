<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Contracts\MutableStepRegistryInterface;
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
use AIArmada\Vouchers\VouchersServiceProvider;

final class RegisterCheckoutOptionalSteps
{
    public function register(MutableStepRegistryInterface $registry): void
    {
        $this->registerInventoryStep($registry);
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
            || class_exists(VouchersServiceProvider::class);
    }

    private function isDiscountsEnabled(): bool
    {
        return config('checkout.integrations.promotions.enabled', true)
            || config('checkout.integrations.vouchers.enabled', true);
    }
}
