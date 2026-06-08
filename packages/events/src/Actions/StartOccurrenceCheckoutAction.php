<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Cart\Facades\Cart;
use AIArmada\Checkout\Facades\Checkout;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Occurrence;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class StartOccurrenceCheckoutAction
{
    public function __construct(
        private readonly CreateOccurrenceCartLineAction $createOccurrenceCartLine,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        Occurrence $occurrence,
        int $quantity = 1,
        array $metadata = [],
        ?Model $customer = null,
        ?Model $owner = null,
    ): ?CheckoutSession {
        $owner ??= OwnerContext::fromTypeAndId(
            is_string($occurrence->getAttribute('owner_type')) ? $occurrence->getAttribute('owner_type') : null,
            is_scalar($occurrence->getAttribute('owner_id')) ? (string) $occurrence->getAttribute('owner_id') : null,
        );

        return OwnerContext::withOwner($owner, function () use ($occurrence, $quantity, $metadata, $customer): ?CheckoutSession {
            $cartItem = $this->createOccurrenceCartLine->handle($occurrence, $quantity, $metadata);

            if ($cartItem === null) {
                return null;
            }

            $cartId = Cart::getId();

            if (! is_string($cartId) || mb_trim($cartId) === '') {
                throw new RuntimeException('Unable to resolve a cart identifier for the occurrence checkout.');
            }

            return Checkout::startCheckout(
                $cartId,
                $customer instanceof Model ? (string) $customer->getKey() : null,
            );
        });
    }
}
