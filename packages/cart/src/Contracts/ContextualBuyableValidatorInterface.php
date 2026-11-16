<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

use AIArmada\CommerceSupport\Contracts\Buyable;
use AIArmada\CommerceSupport\Support\PurchaseContext;

/**
 * Optional application policy for the selected purchase context.
 *
 * Invoked during cart add/update and revalidated by checkout. Domain rules,
 * resource windows and capacity reservations remain in the application.
 * Do not treat this read-only validation as a capacity hold.
 */
interface ContextualBuyableValidatorInterface
{
    public function validate(Buyable $buyable, int $quantity, PurchaseContext $context): CartValidationResult;
}
