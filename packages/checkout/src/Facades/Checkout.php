<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Facades;

use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Services\CheckoutService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AIArmada\Checkout\Models\CheckoutSession startCheckout(string $cartId, ?string $customerId = null)
 * @method static \AIArmada\Checkout\Models\CheckoutSession resumeCheckout(string $sessionId)
 * @method static \AIArmada\Checkout\Data\CheckoutResult processCheckout(\AIArmada\Checkout\Models\CheckoutSession $session)
 * @method static \AIArmada\Checkout\Models\CheckoutSession processStep(\AIArmada\Checkout\Models\CheckoutSession $session, string $stepName)
 * @method static \AIArmada\Checkout\Data\CheckoutResult retryPayment(\AIArmada\Checkout\Models\CheckoutSession $session)
 * @method static \AIArmada\Checkout\Models\CheckoutSession cancelCheckout(\AIArmada\Checkout\Models\CheckoutSession $session)
 * @method static ?string getCurrentStep(\AIArmada\Checkout\Models\CheckoutSession $session)
 * @method static bool canProceed(\AIArmada\Checkout\Models\CheckoutSession $session)
 *
 * @see CheckoutService
 */
final class Checkout extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CheckoutServiceInterface::class;
    }
}
