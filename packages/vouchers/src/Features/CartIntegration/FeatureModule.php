<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration;

use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Vouchers\Features\CartIntegration\Actions\ApplyVoucherToCart;
use AIArmada\Vouchers\Features\CartIntegration\Actions\ManageVoucherReservations;
use AIArmada\Vouchers\Features\CartIntegration\Actions\RemoveVoucherFromCart;
use AIArmada\Vouchers\Features\CartIntegration\Api\VoucherReservations;
use AIArmada\Vouchers\Features\CartIntegration\Api\VoucherValidation;
use AIArmada\Vouchers\Features\CartIntegration\EntryPoints\Listeners\ValidateVoucherOnCheckout;
use AIArmada\Vouchers\Features\CartIntegration\Extensions\VoucherConditionProvider;
use AIArmada\Vouchers\Features\CartIntegration\Queries\VoucherValidator;
use AIArmada\Vouchers\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Vouchers\Runtime\CartVoucherApplicator;
use AIArmada\Vouchers\Runtime\CartVoucherRemoval;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(VoucherValidation::class, VoucherValidator::class);
        $app->singleton(VoucherConditionProvider::class);
        $app->bind(VoucherReservations::class, ManageVoucherReservations::class);
        $app->bind(CartVoucherApplicator::class, ApplyVoucherToCart::class);
        $app->bind(CartVoucherRemoval::class, RemoveVoucherFromCart::class);
    }

    public function boot(Application $app): void
    {
        if (class_exists(ConditionProviderRegistry::class)) {
            $app->make(ConditionProviderRegistry::class)->register(VoucherConditionProvider::class);
        }

        $event = 'AIArmada\\Checkout\\Events\\CheckoutStarted';

        if (class_exists($event)) {
            $app->make(Dispatcher::class)->listen($event, ValidateVoucherOnCheckout::class);
        }
    }
}
