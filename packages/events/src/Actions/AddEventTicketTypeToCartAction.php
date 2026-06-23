<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Inventory\Models\InventoryLevel;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class AddEventTicketTypeToCartAction
{
    use AsAction;

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @param  array<string, mixed>  $extraAttributes
     */
    public function handle(
        Cart $cart,
        EventTicketType $ticketType,
        int $quantity = 1,
        array $participants = [],
        array $extraAttributes = [],
        bool $skipQuotaValidation = false,
    ): CartItem {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        if ($ticketType->status !== 'active') {
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

        $mergedAttributes = $this->buildAttributes($ticketType, $participants, $extraAttributes, $existingItem);

        if ($ticketType->max_quantity !== null && $totalQuantity > $ticketType->max_quantity) {
            throw new InvalidArgumentException(sprintf(
                'Maximum quantity for "%s" is %d (you have %d in cart).',
                $ticketType->name,
                $ticketType->max_quantity,
                $existingQuantity,
            ));
        }

        if ($ticketType->sales_starts_at !== null && now()->isBefore($ticketType->sales_starts_at)) {
            throw new InvalidArgumentException(sprintf(
                'Sales for "%s" have not started yet.',
                $ticketType->name,
            ));
        }

        if ($ticketType->sales_ends_at !== null && now()->isAfter($ticketType->sales_ends_at)) {
            throw new InvalidArgumentException(sprintf(
                'Sales for "%s" have ended.',
                $ticketType->name,
            ));
        }

        if (! $skipQuotaValidation && class_exists(InventoryLevel::class)) {
            if (! $ticketType->hasInventory($totalQuantity)) {
                throw new InvalidArgumentException(sprintf(
                    '"%s" is sold out or has insufficient stock.',
                    $ticketType->name,
                ));
            }
        }

        $cartItem = $this->addOrUpdateCartItem($cart, $ticketType, $totalQuantity, $mergedAttributes);

        if (CommerceIntegration::aiArmadaCheckoutAvailable()) {
            app(AutoAddRequiredTicketBundlesAction::class)->handle($cart, $ticketType, $quantity);
        }

        return $cartItem;
    }

    /**
     * @param  array<string, mixed>  $extraAttributes
     * @return array<string, mixed>
     */
    private function buildAttributes(
        EventTicketType $ticketType,
        array $participants,
        array $extraAttributes,
        ?CartItem $existingItem,
    ): array {
        $mergedParticipants = $participants;

        if ($existingItem !== null) {
            $existingParticipants = $existingItem->getAttribute('participants');

            if (is_array($existingParticipants) && $existingParticipants !== []) {
                $mergedParticipants = array_merge($existingParticipants, $participants);
            }
        }

        return array_merge([
            'purchasable_type' => EventTicketType::class,
            'purchasable_id' => $ticketType->getKey(),
            'event_id' => $ticketType->event_id,
            'event_occurrence_id' => $ticketType->event_occurrence_id,
            'event_session_id' => $ticketType->event_session_id,
            'code' => $ticketType->code,
            'participants' => $mergedParticipants,
        ], $extraAttributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function addOrUpdateCartItem(
        Cart $cart,
        EventTicketType $ticketType,
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
            price: $ticketType->price,
            quantity: $totalQuantity,
            attributes: $attributes,
            associatedModel: $ticketType,
        );
    }
}
