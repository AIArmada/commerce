<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\TicketTypes\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;
use AIArmada\CommerceSupport\Contracts\Buyable;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketTypeProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final readonly class AutoAddRequiredTicketBundlesAction
{
    public function handle(Cart $cart, TicketType $ticketType): void
    {
        $ticketLine = $cart->get($ticketType->getKey());

        if (! $ticketLine instanceof CartItem) {
            $this->removeBundlesFor($cart, $ticketType);

            return;
        }

        $ticketType->load('requiredBundleProducts.product', 'requiredBundleProducts.variant');

        /** @var Collection<int, TicketTypeProduct> $bundles */
        $bundles = $ticketType->requiredBundleProducts;
        $expectedLineIds = [];

        foreach ($bundles as $bundle) {
            $target = $bundle->variant ?? $bundle->product;

            if (! $target instanceof Buyable) {
                continue;
            }

            $lineId = $this->lineIdentifier($ticketType, $bundle, $target);
            $expectedLineIds[$lineId] = true;
            $quantity = (int) $bundle->quantity;

            if ($quantity > intdiv(PHP_INT_MAX, max(1, $ticketLine->quantity))) {
                throw new InvalidArgumentException('Required bundle quantity exceeds the supported range.');
            }

            $requiredQuantity = max(0, $quantity * $ticketLine->quantity);

            if ($requiredQuantity === 0) {
                $cart->remove($lineId);

                continue;
            }

            $attributes = $this->attributes($ticketType, $bundle);
            $existingItem = $cart->get($lineId);

            if (! $existingItem instanceof CartItem) {
                $cart->addBuyable($target, $requiredQuantity, $attributes, $lineId);

                continue;
            }

            $cart->updateBuyable($target, $requiredQuantity, $lineId, $attributes);
        }

        $this->removeUnexpectedBundlesFor($cart, $ticketType, $expectedLineIds);
    }

    private function lineIdentifier(TicketType $ticketType, TicketTypeProduct $bundle, Buyable $target): string
    {
        $type = $target instanceof Model
            ? $target->getMorphClass()
            : $target::class;

        return sprintf(
            'ticket-bundle:%s:%s:%s:%s',
            $ticketType->getKey(),
            $bundle->getKey(),
            $type,
            $target->getBuyableIdentifier(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(TicketType $ticketType, TicketTypeProduct $bundle): array
    {
        return [
            'bundle_source' => 'ticket_type:' . $ticketType->getKey(),
            'bundle_parent_line_id' => (string) $ticketType->getKey(),
            'bundle_ticket_type_id' => (string) $ticketType->getKey(),
            'bundle_ticket_type_product_id' => (string) $bundle->getKey(),
        ];
    }

    private function removeBundlesFor(Cart $cart, TicketType $ticketType): void
    {
        $this->removeUnexpectedBundlesFor($cart, $ticketType, []);
    }

    /**
     * @param  array<string, true>  $expectedLineIds
     */
    private function removeUnexpectedBundlesFor(Cart $cart, TicketType $ticketType, array $expectedLineIds): void
    {
        $parentLineId = (string) $ticketType->getKey();

        $orphans = $cart->search(
            fn (CartItem $item): bool => $item->getAttribute('bundle_parent_line_id') === $parentLineId
                && $item->getAttribute('bundle_source') === 'ticket_type:' . $parentLineId
                && ! isset($expectedLineIds[$item->id]),
        );

        foreach ($orphans as $orphan) {
            $cart->remove($orphan->id);
        }
    }
}
