<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Models\TicketTypeProduct;
use Illuminate\Support\Collection;

final class AutoAddRequiredTicketBundlesAction
{
    public function handle(Cart $cart, TicketType $ticketType, int $quantity = 1): void
    {
        $ticketType->loadMissing('requiredBundleProducts.product', 'requiredBundleProducts.variant');

        /** @var Collection<int, TicketTypeProduct> $bundles */
        $bundles = $ticketType->requiredBundleProducts;

        if ($bundles->isEmpty()) {
            return;
        }

        foreach ($bundles as $bundle) {
            $product = $bundle->product;
            $variant = $bundle->variant;

            $target = $variant ?? $product;

            if ($target === null) {
                continue;
            }

            $existingItem = $cart->has($target->getKey())
                ? $cart->get($target->getKey())
                : null;

            if ($existingItem !== null) {
                continue;
            }

            $cart->add(
                id: $target->getKey(),
                name: method_exists($target, 'getBuyableName') ? $target->getBuyableName() : $target->getKey(),
                price: method_exists($target, 'getBuyablePrice') ? $target->getBuyablePrice() : 0,
                quantity: $bundle->quantity * $quantity,
                attributes: ['bundle_source' => 'ticket_type:' . $ticketType->getKey()],
                associatedModel: $target,
            );
        }
    }
}
