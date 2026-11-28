<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Checkout;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\AbstractCheckoutStep;
use AIArmada\Events\Contracts\EventOrderDriver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Data\EventOrderData;
use AIArmada\Events\Data\EventOrderItemData;
use AIArmada\Events\Features\Commerce\Actions\CreateRegistrationsFromOrderAction;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Platform\EventTicketScope;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Throwable;

final class CreateEventRegistrationsStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly CreateRegistrationsFromOrderAction $createRegistrations,
        private readonly RegistrationServiceInterface $registrationService,
        private readonly RegistrationsApi $registrations,
        private readonly EventOrderDriver $orders,
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

        $order = $this->orders->orderSnapshot((string) $session->order_id);

        if ($order === null) {
            return $this->skipped('Order not found.');
        }

        $cartSnapshot = $session->cart_snapshot ?? [];
        $cartItems = array_values($cartSnapshot['items'] ?? []);

        if ($order->items === []) {
            return $this->skipped('Order has no items.');
        }

        $created = 0;
        $stepData = $session->getStepData($this->getIdentifier());
        $stepData['registration_ids'] = $this->stringList($stepData['registration_ids'] ?? []);
        $stepData['order_item_options'] = is_array($stepData['order_item_options'] ?? null)
            ? $stepData['order_item_options']
            : [];

        foreach ($order->items as $orderItem) {
            $ticketType = $this->resolveTicketType($orderItem);

            if (! $ticketType instanceof TicketType) {
                continue;
            }

            $scopeRef = $this->scopeRef($ticketType);

            if ($scopeRef === null) {
                continue;
            }

            $originalOptions = $this->orders->markItemFulfillment($orderItem->id);

            if ($originalOptions !== null) {
                $stepData['order_item_options'][$orderItem->id] = [
                    'order_item_id' => $orderItem->id,
                    'options' => $originalOptions['options'],
                ];
            }

            $this->persistStepData($session, $stepData);

            $existingRegistrationIds = $this->registrations->registrationIdsForOrderItem(
                eventId: $scopeRef['eventId'],
                occurrenceId: $scopeRef['occurrenceId'],
                sessionId: $scopeRef['sessionId'],
                orderItemId: $orderItem->id,
                orderItemType: $orderItem->orderItemClass,
            );

            $participants = $this->resolveParticipants($orderItem, $cartItems, $order);

            $registrationIds = $this->createRegistrations->handle(
                scopeType: $scopeRef['type'],
                scopeId: $scopeRef['id'],
                orderItemId: $orderItem->id,
                participants: $participants,
                purchaser: $this->resolveRegistrant($session, $order),
            );

            foreach ($registrationIds as $registrationId) {
                if (in_array($registrationId, $existingRegistrationIds, true)) {
                    continue;
                }

                if (in_array($registrationId, $stepData['registration_ids'], true)) {
                    continue;
                }

                $stepData['registration_ids'][] = $registrationId;
            }

            $this->persistStepData($session, $stepData);

            $created++;
        }

        if ($created === 0) {
            return $this->skipped('No event ticket items found in order.');
        }

        return $this->success(
            sprintf('%d event registrations created.', $created),
            $stepData,
        );
    }

    public function compensate(CheckoutSession $session): StepResult
    {
        $stepData = $session->getStepData($this->getIdentifier());
        $registrationIds = $this->stringList($stepData['registration_ids'] ?? []);
        $errors = [];
        $cancelled = 0;

        foreach ($registrationIds as $registrationId) {
            try {
                $this->registrationService->cancel($registrationId, 'Checkout compensation');
                $cancelled++;
            } catch (Throwable $exception) {
                $errors[$registrationId] = $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : $exception::class;
            }
        }

        $restored = 0;

        try {
            $restored = $this->restoreOrderItemOptions($stepData['order_item_options'] ?? []);
        } catch (Throwable $exception) {
            $errors['order_item_options'] = $exception->getMessage() !== '' ? $exception->getMessage() : $exception::class;
        }

        if ($errors !== []) {
            return $this->failed('Event registration compensation failed.', $errors);
        }

        return $this->compensated(
            'Event registrations compensated.',
            [
                'registration_ids' => $registrationIds,
                'cancelled' => $cancelled,
                'order_items_restored' => $restored,
            ],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     * @return array<int, array<string, mixed>>
     */
    private function resolveParticipants(
        EventOrderItemData $orderItem,
        array $cartItems,
        EventOrderData $order,
    ): array {
        foreach ($cartItems as $cartItem) {
            $cartPurchasableId = data_get($cartItem, 'attributes.purchasable_id');

            if ($cartPurchasableId === $orderItem->ticketTypeId) {
                $participants = data_get($cartItem, 'attributes.participants', []);

                if (is_array($participants) && $participants !== []) {
                    return array_values(array_filter($participants, 'is_array'));
                }

                break;
            }
        }

        return $this->participantsForOrderItem($order, $orderItem);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function participantsForOrderItem(EventOrderData $order, EventOrderItemData $orderItem): array
    {
        $quantity = max(1, $orderItem->quantity);
        $participants = [];

        if ($order->customerName !== null || $order->customerEmail !== null) {
            $name = $order->customerName ?? 'Attendee';

            for ($index = 0; $index < $quantity; $index++) {
                $participants[] = array_filter([
                    'name' => $index === 0 ? $name : sprintf('%s #%d', $name, $index + 1),
                    'email' => $index === 0 ? $order->customerEmail : null,
                    'phone' => $index === 0 ? $order->customerPhone : null,
                    'is_primary' => $index === 0,
                ], static fn (mixed $value): bool => $value !== null);
            }

            return $participants;
        }

        for ($index = 0; $index < $quantity; $index++) {
            $participants[] = [
                'name' => sprintf('Attendee #%d', $index + 1),
                'is_primary' => $index === 0,
            ];
        }

        return $participants;
    }

    private function resolveRegistrant(CheckoutSession $session, EventOrderData $order): ?Model
    {
        $actor = data_get($session->payment_data ?? [], 'checkout_actor');

        if (is_array($actor)) {
            $type = $actor['type'] ?? null;
            $id = $actor['id'] ?? null;

            if (is_string($type) && $type !== '' && (is_string($id) || is_int($id))) {
                $modelClass = Relation::getMorphedModel($type) ?? $type;

                if (is_string($modelClass) && class_exists($modelClass) && is_a($modelClass, Model::class, true)) {
                    $registrant = $modelClass::query()->find((string) $id);

                    if ($registrant instanceof Model) {
                        return $registrant;
                    }
                }
            }
        }

        if ($order->customerId === null || $order->customerType === null) {
            return null;
        }

        $class = Relation::getMorphedModel($order->customerType) ?? $order->customerType;

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            return null;
        }

        $customer = $class::query()->find($order->customerId);

        return $customer instanceof Model ? $customer : null;
    }

    private function resolveTicketType(EventOrderItemData $orderItem): ?TicketType
    {
        if ($orderItem->ticketTypeId === null) {
            return null;
        }

        $ticketType = TicketType::query()->with('ticketable')->find($orderItem->ticketTypeId);

        if (! $ticketType instanceof TicketType || EventTicketScope::target($ticketType) === null) {
            return null;
        }

        return $ticketType;
    }

    /**
     * @return array{type: string, id: string, eventId: string, occurrenceId: ?string, sessionId: ?string}|null
     */
    private function scopeRef(TicketType $ticketType): ?array
    {
        $session = EventTicketScope::session($ticketType);

        if ($session !== null) {
            return [
                'type' => 'session',
                'id' => (string) $session->getKey(),
                'eventId' => (string) $session->getAttribute('event_id'),
                'occurrenceId' => $session->getAttribute('event_occurrence_id') !== null
                    ? (string) $session->getAttribute('event_occurrence_id')
                    : null,
                'sessionId' => (string) $session->getKey(),
            ];
        }

        $occurrence = EventTicketScope::occurrence($ticketType);

        if ($occurrence !== null) {
            return [
                'type' => 'occurrence',
                'id' => (string) $occurrence->getKey(),
                'eventId' => (string) $occurrence->getAttribute('event_id'),
                'occurrenceId' => (string) $occurrence->getKey(),
                'sessionId' => null,
            ];
        }

        $event = EventTicketScope::event($ticketType);

        if ($event === null) {
            return null;
        }

        return [
            'type' => 'event',
            'id' => (string) $event->getKey(),
            'eventId' => (string) $event->getKey(),
            'occurrenceId' => null,
            'sessionId' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $stepData
     */
    private function persistStepData(CheckoutSession $session, array &$stepData): void
    {
        $stepData['registration_ids'] = array_values(array_unique(
            $this->stringList($stepData['registration_ids'] ?? []),
        ));

        $session->setStepData($this->getIdentifier(), $stepData);
    }

    /**
     * @param  array<string, mixed>  $restorations
     */
    private function restoreOrderItemOptions(mixed $restorations): int
    {
        if (! is_array($restorations) || $restorations === []) {
            return 0;
        }

        $restored = 0;

        foreach ($restorations as $restoration) {
            if (! is_array($restoration)) {
                continue;
            }

            $orderItemId = $restoration['order_item_id'] ?? null;

            if (! is_string($orderItemId) && ! is_int($orderItemId)) {
                continue;
            }

            $options = $restoration['options'] ?? null;

            $this->orders->restoreItemOptions((string) $orderItemId, is_array($options) ? $options : null);
            $restored++;
        }

        return $restored;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => (string) $item, $value),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
