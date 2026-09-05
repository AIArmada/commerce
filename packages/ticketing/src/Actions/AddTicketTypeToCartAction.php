<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Support\Integration\TicketingIntegration;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class AddTicketTypeToCartAction
{
    use AsAction;

    /** @param array<int, array<string, mixed>> $participants */
    public function handle(
        Cart $cart,
        TicketType $ticketType,
        int $quantity = 1,
        array $participants = [],
        array $extraAttributes = [],
        bool $skipQuotaValidation = false,
    ): CartItem {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        if ($ticketType->status !== 'active' || $ticketType->isHidden()) {
            throw new InvalidArgumentException(sprintf(
                'Ticket type "%s" is not available for purchase.',
                $ticketType->name,
            ));
        }

        $existingItem = $cart->has($ticketType->getKey())
            ? $cart->get($ticketType->getKey())
            : null;

        $existingQuantity = $existingItem !== null ? $existingItem->quantity : 0;
        $totalQuantity = $existingQuantity + $quantity;

        if ($ticketType->min_quantity !== null && $totalQuantity < $ticketType->min_quantity) {
            throw new InvalidArgumentException(sprintf(
                'Minimum quantity for "%s" is %d.',
                $ticketType->name,
                $ticketType->min_quantity,
            ));
        }

        $inventoryConfigured = $this->inventoryConfigured($ticketType);
        $mergedAttributes = $this->buildAttributes(
            $ticketType,
            $participants,
            $extraAttributes,
            $existingItem,
            $inventoryConfigured,
        );

        if ($ticketType->max_quantity !== null && $totalQuantity > $ticketType->max_quantity) {
            throw new InvalidArgumentException(sprintf(
                'Maximum quantity for "%s" is %d (you have %d in cart).',
                $ticketType->name,
                $ticketType->max_quantity,
                $existingQuantity,
            ));
        }

        if ($ticketType->sales_starts_at !== null && CarbonImmutable::now()->isBefore($ticketType->sales_starts_at)) {
            throw new InvalidArgumentException(sprintf(
                'Sales for "%s" have not started yet.',
                $ticketType->name,
            ));
        }

        if ($ticketType->sales_ends_at !== null && CarbonImmutable::now()->isAfter($ticketType->sales_ends_at)) {
            throw new InvalidArgumentException(sprintf(
                'Sales for "%s" have ended.',
                $ticketType->name,
            ));
        }

        if (! $skipQuotaValidation && $inventoryConfigured) {
            if (! $ticketType->hasInventory($totalQuantity)) {
                throw new InvalidArgumentException(sprintf(
                    '"%s" is sold out or has insufficient stock.',
                    $ticketType->name,
                ));
            }
        }

        $cartItem = $this->addOrUpdateCartItem($cart, $ticketType, $totalQuantity, $mergedAttributes);

        if (TicketingIntegration::productsAvailable()) {
            app(AutoAddRequiredTicketBundlesAction::class)->handle($cart, $ticketType, $quantity);
        }

        return $cartItem;
    }

    /** @param array<string, mixed> $extraAttributes */
    private function buildAttributes(
        TicketType $ticketType,
        array $participants,
        array $extraAttributes,
        ?CartItem $existingItem,
        bool $inventoryConfigured,
    ): array {
        $mergedParticipants = $participants;

        if ($existingItem !== null) {
            $existingParticipants = $existingItem->getAttribute('participants');
            if (is_array($existingParticipants) && $existingParticipants !== []) {
                $mergedParticipants = array_merge($existingParticipants, $participants);
            }
        }

        $attributes = [
            'purchasable_type' => TicketType::class,
            'purchasable_id' => $ticketType->getKey(),
            'code' => $ticketType->code,
            'participants' => $mergedParticipants,
        ];

        if ($inventoryConfigured) {
            // An absent inventory level means unlimited inventory for a
            // ticket type. Only mark configured ticket types for checkout
            // reservation so an unlimited ticket is not treated as sold out.
            $attributes['inventoryable_type'] = $ticketType->getMorphClass();
            $attributes['inventoryable_id'] = (string) $ticketType->getKey();
        }

        return array_merge($attributes, $extraAttributes);
    }

    private function inventoryConfigured(TicketType $ticketType): bool
    {
        return TicketingIntegration::inventoryAvailable()
            && class_exists(InventoryLevel::class)
            && $ticketType->inventoryLevels()->exists();
    }

    /** @param array<string, mixed> $attributes */
    private function addOrUpdateCartItem(
        Cart $cart,
        TicketType $ticketType,
        int $totalQuantity,
        array $attributes,
    ): CartItem {
        if ($cart->has($ticketType->getKey())) {
            $updated = $cart->update($ticketType->getKey(), [
                'quantity' => ['value' => $totalQuantity],
                'attributes' => $attributes,
            ]);

            if ($updated !== null) {
                return $updated;
            }
        }

        return $cart->add(
            id: $ticketType->getKey(),
            name: $ticketType->name,
            price: $ticketType->price ?? 0,
            quantity: $totalQuantity,
            attributes: $attributes,
            associatedModel: $ticketType,
        );
    }
}
