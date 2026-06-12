<?php

declare(strict_types=1);

namespace AIArmada\Events\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\AbstractCheckoutStep;
use AIArmada\Events\Actions\CreateRegistrationsForOrderItemAction;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use Illuminate\Database\Eloquent\Model;

final class CreateEventRegistrationsStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly CreateRegistrationsForOrderItemAction $createRegistrations,
    ) {}

    public function getIdentifier(): string
    {
        return 'create_event_registrations';
    }

    public function getName(): string
    {
        return 'Create Event Registrations';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['create_order'];
    }

    public function handle(CheckoutSession $session): StepResult
    {
        if ($session->order_id === null) {
            return $this->skipped('No order to create registrations for.');
        }

        $orderClass = CommerceIntegration::requireModelClass('order_model', 'order fulfillment');

        /** @var Model|null $order */
        $order = $orderClass::query()
            ->with('items.purchasable', 'customer')
            ->find($session->order_id);

        if ($order === null) {
            return $this->skipped('Order not found.');
        }

        $cartSnapshot = $session->cart_snapshot ?? [];
        $cartItems = array_values($cartSnapshot['items'] ?? []);
        $orderItems = $order->getRelation('items');

        if ($orderItems->isEmpty()) {
            return $this->skipped('Order has no items.');
        }

        $created = 0;

        foreach ($orderItems as $orderItem) {
            $purchasable = $orderItem->getRelation('purchasable');

            if (! $purchasable instanceof EventTicketType) {
                continue;
            }

            $ticketType = $purchasable;

            if ($ticketType->event_occurrence_id === null) {
                continue;
            }

            /** @var EventOccurrence|null $occurrence */
            $occurrence = $ticketType->relationLoaded('occurrence')
                ? $ticketType->getRelation('occurrence')
                : EventOccurrence::query()->find($ticketType->event_occurrence_id);

            if ($occurrence === null) {
                continue;
            }

            $participants = $this->resolveParticipants(
                session: $session,
                orderItem: $orderItem,
                cartItems: $cartItems,
                order: $order,
            );

            $this->createRegistrations->handle(
                occurrence: $occurrence,
                orderItem: $orderItem,
                participants: $participants,
                purchaser: $order->getRelation('customer'),
            );

            $created++;
        }

        if ($created === 0) {
            return $this->skipped('No event ticket items found in order.');
        }

        return $this->success(sprintf('%d event registrations created.', $created));
    }

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     * @return array<int, array<string, mixed>>
     */
    private function resolveParticipants(
        CheckoutSession $session,
        mixed $orderItem,
        array $cartItems,
        mixed $order,
    ): array {
        $orderItemPurchasableId = data_get($orderItem, 'purchasable_id');

        foreach ($cartItems as $cartItem) {
            $cartPurchasableId = data_get($cartItem, 'attributes.purchasable_id')
                ?? data_get($cartItem, 'associated_model.id')
                ?? data_get($cartItem, 'purchasable_id');

            if ($cartPurchasableId === $orderItemPurchasableId) {
                $participants = data_get($cartItem, 'attributes.participants', []);

                if (is_array($participants) && $participants !== []) {
                    return $participants;
                }

                break;
            }
        }

        return $this->fallbackParticipants($order, $orderItem);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fallbackParticipants(mixed $order, mixed $orderItem): array
    {
        $customer = $order->getRelation('customer');
        $quantity = max(1, (int) ($orderItem->quantity ?? 1));
        $participants = [];

        if ($customer !== null) {
            $name = mb_trim((string) data_get($customer, 'full_name', ''));
            $name = $name !== '' ? $name : 'Attendee';
            $email = data_get($customer, 'email');
            $phone = data_get($customer, 'phone');

            for ($i = 0; $i < $quantity; $i++) {
                $participants[] = array_filter([
                    'name' => $i === 0 ? $name : sprintf('%s #%d', $name, $i + 1),
                    'email' => $i === 0 ? $email : null,
                    'phone' => $i === 0 ? $phone : null,
                    'is_primary' => $i === 0,
                ], static fn (mixed $value): bool => $value !== null);
            }
        } else {
            for ($i = 0; $i < $quantity; $i++) {
                $participants[] = [
                    'name' => sprintf('Attendee #%d', $i + 1),
                    'is_primary' => $i === 0,
                ];
            }
        }

        return $participants;
    }
}
