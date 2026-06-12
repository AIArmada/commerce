<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Events\Actions\AddEventTicketTypeToCartAction;
use AIArmada\Events\Contracts\EventCheckoutIntentResolver;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use RuntimeException;

final class DefaultEventCheckoutIntentResolver implements EventCheckoutIntentResolver
{
    public function __construct(
        private readonly CartManagerInterface $cartManager,
        private readonly CheckoutServiceInterface $checkoutService,
        private readonly AddEventTicketTypeToCartAction $addToCart,
    ) {}

    public function resolve(EventOccurrence $occurrence, EventRegistration $registration): mixed
    {
        $registration->loadMissing('items.ticketType', 'participants');

        $cart = $this->cartManager->getCartInstance(
            name: 'event_checkout',
            identifier: 'registration-' . $registration->getKey(),
        );

        foreach ($registration->items as $item) {
            $ticketType = $item->ticketType;

            if ($ticketType === null) {
                continue;
            }

            $participants = $this->resolveParticipants($registration);

            $this->addToCart->handle(
                cart: $cart,
                ticketType: $ticketType,
                quantity: $item->quantity,
                participants: $participants,
                skipQuotaValidation: true,
            );
        }

        $customerId = $registration->registrant?->getKey();

        $cartId = $cart->getId();

        if ($cartId === null) {
            throw new RuntimeException('Failed to create a checkout cart for the registration.');
        }

        return $this->checkoutService->startCheckout(
            cartId: $cartId,
            customerId: $customerId,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveParticipants(EventRegistration $registration): array
    {
        $participants = $registration->getRelation('participants');

        if ($participants->isEmpty()) {
            return [];
        }

        return $participants->map(function (mixed $participant): array {
            return array_filter([
                'name' => data_get($participant, 'name'),
                'email' => data_get($participant, 'email'),
                'phone' => data_get($participant, 'phone'),
                'relationship_to_registrant' => data_get($participant, 'relationship_to_registrant'),
                'participant_type' => data_get($participant, 'participant_type'),
                'participant_id' => data_get($participant, 'participant_id'),
                'event_occurrence_id' => data_get($participant, 'event_occurrence_id'),
                'event_session_id' => data_get($participant, 'event_session_id'),
                'age' => data_get($participant, 'age'),
                'gender' => data_get($participant, 'gender'),
                'status' => data_get($participant, 'status'),
                'notes' => data_get($participant, 'notes'),
                'metadata' => data_get($participant, 'metadata'),
                'is_primary' => (bool) data_get($participant, 'is_primary', false),
            ], static fn (mixed $value): bool => $value !== null);
        })->values()->toArray();
    }
}
