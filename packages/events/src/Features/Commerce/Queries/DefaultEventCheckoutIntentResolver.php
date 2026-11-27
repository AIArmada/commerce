<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Queries;

use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Events\Contracts\EventCartDriver;
use AIArmada\Events\Contracts\EventCheckoutIntentResolver;
use AIArmada\Events\Features\Commerce\Actions\AddEventTicketTypeToCartAction;
use AIArmada\Events\Features\Registrations\Api\EventRegistrationParticipantData;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Ticketing\Models\TicketType;
use RuntimeException;

final class DefaultEventCheckoutIntentResolver implements EventCheckoutIntentResolver
{
    public function __construct(
        private readonly CartManagerInterface $cartManager,
        private readonly EventCartDriver $cart,
        private readonly RegistrationsApi $registrations,
        private readonly AddEventTicketTypeToCartAction $addToCart,
    ) {}

    public function resolve(string $targetType, string $targetId, string $registrationId): mixed
    {
        $summary = $this->registrations->summarize($registrationId);

        if ($summary === null) {
            throw new RuntimeException('Failed to create a checkout cart for the registration.');
        }

        $cart = $this->cartManager->getCartInstance(
            name: 'event_checkout',
            identifier: 'registration-' . $registrationId,
        );

        $participants = $this->participantsPayload($registrationId);

        foreach ($this->registrations->orderItemsForRegistration($registrationId) as $item) {
            $ticketTypeId = $item->ticketTypeId;

            if ($ticketTypeId === null) {
                continue;
            }

            /** @var TicketType|null $ticketType */
            $ticketType = TicketType::query()->find($ticketTypeId);

            if (! $ticketType instanceof TicketType) {
                continue;
            }

            $this->addToCart->handle(
                cart: $cart,
                ticketType: $ticketType,
                quantity: $item->quantity,
                participants: $participants,
                skipQuotaValidation: true,
            );
        }

        $cartId = $cart->getId();

        if (! is_string($cartId) || $cartId === '') {
            throw new RuntimeException('Failed to create a checkout cart for the registration.');
        }

        return $this->cart->startCheckout($cartId, $summary->registrantId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function participantsPayload(string $registrationId): array
    {
        return array_values(array_map(
            static fn (EventRegistrationParticipantData $participant): array => array_filter([
                'name' => $participant->name,
                'email' => $participant->email,
                'phone' => $participant->phone,
                'relationship_to_registrant' => $participant->relationshipToRegistrant,
                'participant_type' => $participant->participantType,
                'participant_id' => $participant->participantId,
                'event_occurrence_id' => $participant->occurrenceId,
                'event_session_id' => $participant->sessionId,
                'age' => $participant->age,
                'gender' => $participant->gender,
                'status' => $participant->status,
                'notes' => $participant->notes,
                'metadata' => $participant->metadata,
                'is_primary' => $participant->isPrimary,
            ], static fn (mixed $value): bool => $value !== null),
            $this->registrations->participantsForRegistration($registrationId),
        ));
    }
}
