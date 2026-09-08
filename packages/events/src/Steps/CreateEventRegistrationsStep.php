<?php

declare(strict_types=1);

namespace AIArmada\Events\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\AbstractCheckoutStep;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Actions\CreateRegistrationsFromOrderAction;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Support\EventTicketScope;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Throwable;

final class CreateEventRegistrationsStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly CreateRegistrationsFromOrderAction $createRegistrations,
        private readonly RegistrationServiceInterface $registrationService,
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

        /** @var class-string<Model> $orderClass */
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
        $stepData = $session->getStepData($this->getIdentifier());
        $stepData['registration_ids'] = $this->stringList($stepData['registration_ids'] ?? []);
        $stepData['order_item_options'] = is_array($stepData['order_item_options'] ?? null)
            ? $stepData['order_item_options']
            : [];

        foreach ($orderItems as $orderItem) {
            $purchasable = $orderItem->getRelation('purchasable');

            if (! $purchasable instanceof TicketType || EventTicketScope::target($purchasable) === null) {
                continue;
            }

            $ticketType = $purchasable;

            $target = $this->resolveRegistrationTarget($ticketType);

            if ($target === null) {
                continue;
            }

            $originalOptions = $this->markEventFulfillment($orderItem);

            if ($originalOptions !== null) {
                $stepData['order_item_options'][(string) $orderItem->getKey()] = [
                    'order_item_id' => (string) $orderItem->getKey(),
                    'options' => $originalOptions['options'],
                ];
            }

            $this->persistStepData($session, $stepData);

            $existingRegistrationIds = $this->existingRegistrationIds($order, $orderItem);

            $participants = $this->resolveParticipants(
                orderItem: $orderItem,
                cartItems: $cartItems,
                order: $order,
            );

            $registrations = $this->createRegistrations->handle(
                $target,
                $orderItem,
                $participants,
                $this->resolveRegistrant($session, $order),
            );

            foreach ($registrations as $registration) {
                if (! $registration instanceof EventRegistration) {
                    continue;
                }

                $registrationId = $registration->getKey();

                if ($registrationId === null || in_array((string) $registrationId, $existingRegistrationIds, true)) {
                    continue;
                }

                $stepData['registration_ids'][] = (string) $registrationId;
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

        if ($registrationIds !== []) {
            $registrationClass = ModelResolver::registrationClass();
            $registrations = $registrationClass::query()
                ->whereKey($registrationIds)
                ->get();

            foreach ($registrations as $registration) {
                try {
                    $this->registrationService->cancel($registration, 'Checkout compensation');
                    $cancelled++;
                } catch (Throwable $e) {
                    $errors[(string) $registration->getKey()] = $e->getMessage() !== ''
                        ? $e->getMessage()
                        : $e::class;
                }
            }
        }

        $restored = 0;

        try {
            $restored = $this->restoreOrderItemOptions($session, $stepData['order_item_options'] ?? []);
        } catch (Throwable $e) {
            $errors['order_item_options'] = $e->getMessage() !== '' ? $e->getMessage() : $e::class;
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
        mixed $orderItem,
        array $cartItems,
        mixed $order,
    ): array {
        $orderItemPurchasableId = data_get($orderItem, 'purchasable_id');

        foreach ($cartItems as $cartItem) {
            $cartPurchasableId = data_get($cartItem, 'attributes.purchasable_id');

            if ($cartPurchasableId === $orderItemPurchasableId) {
                $participants = data_get($cartItem, 'attributes.participants', []);

                if (is_array($participants) && $participants !== []) {
                    return $participants;
                }

                break;
            }
        }

        return $this->participantsForOrderItem($order, $orderItem);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function participantsForOrderItem(mixed $order, mixed $orderItem): array
    {
        $customer = $order->getRelation('customer');
        $quantity = max(1, (int) ($orderItem->quantity ?? 1));
        $participants = [];

        if ($customer !== null) {
            $name = mb_trim((string) data_get($customer, 'full_name', ''));
            $name = $name !== '' ? $name : 'Attendee';
            $email = $this->resolveCustomerEmail($customer);
            $phone = $this->resolveCustomerPhone($customer);

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

    private function resolveRegistrant(CheckoutSession $session, Model $order): ?Model
    {
        $actor = data_get($session->payment_data ?? [], 'checkout_actor');

        if (is_array($actor)) {
            $type = $actor['type'] ?? null;
            $id = $actor['id'] ?? null;

            if (is_string($type) && $type !== '' && (is_string($id) || is_int($id))) {
                $modelClass = Relation::getMorphedModel($type) ?? $type;

                if (class_exists($modelClass) && is_a($modelClass, Model::class, true)) {
                    /** @var class-string<Model> $modelClass */
                    $registrant = $modelClass::query()->find((string) $id);

                    if ($registrant instanceof Model) {
                        return $registrant;
                    }
                }
            }
        }

        $customer = $order->getRelation('customer');

        return $customer instanceof Model ? $customer : null;
    }

    private function resolveRegistrationTarget(TicketType $ticketType): ?Model
    {
        $ticketType->loadMissing('ticketable');

        return EventTicketScope::target($ticketType);
    }

    private function markEventFulfillment(mixed $orderItem): ?array
    {
        $originalOptions = $orderItem->getAttribute('options');
        $options = $originalOptions;

        $options = is_array($options) ? $options : [];

        if (($options['event_fulfillment'] ?? null) === 'event_registration') {
            return null;
        }

        $orderItem->forceFill([
            'options' => [...$options, 'event_fulfillment' => 'event_registration'],
        ])->save();

        return ['options' => $originalOptions];
    }

    /**
     * @return list<string>
     */
    private function existingRegistrationIds(Model $order, mixed $orderItem): array
    {
        $registrationClass = ModelResolver::registrationClass();

        return $registrationClass::byOrder($order)
            ->whereHas(
                'items',
                fn ($query) => $query
                    ->where('external_order_item_id', $orderItem->getKey())
                    ->where('external_order_item_type', $orderItem::class),
            )
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
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
    private function restoreOrderItemOptions(CheckoutSession $session, mixed $restorations): int
    {
        if (! is_array($restorations) || $restorations === [] || $session->order_id === null) {
            return 0;
        }

        /** @var class-string<Model> $orderClass */
        $orderClass = CommerceIntegration::requireModelClass('order_model', 'order fulfillment');

        /** @var Model $order */
        $order = method_exists($orderClass, 'ownerScopeConfig') && ! $orderClass::ownerScopeConfig()->enabled
            ? $orderClass::query()->findOrFail($session->order_id)
            : OwnerWriteGuard::findOrFailForOwner($orderClass, $session->order_id);
        $order->loadMissing('items');
        $restored = 0;

        foreach ($restorations as $restoration) {
            if (! is_array($restoration)) {
                continue;
            }

            $orderItemId = $restoration['order_item_id'] ?? null;

            if (! is_string($orderItemId) && ! is_int($orderItemId)) {
                continue;
            }

            $orderItem = $order->getRelation('items')->first(
                fn (mixed $item): bool => (string) $item->getKey() === (string) $orderItemId,
            );

            if (! $orderItem instanceof Model) {
                continue;
            }

            $orderItem->forceFill(['options' => $restoration['options'] ?? null])->save();
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

    private function resolveCustomerEmail(mixed $customer): ?string
    {
        if (! $customer instanceof Model || ! method_exists($customer, 'resolveEmail')) {
            return null;
        }

        return $this->cleanString(call_user_func([$customer, 'resolveEmail']));
    }

    private function resolveCustomerPhone(mixed $customer): ?string
    {
        if (! $customer instanceof Model || ! method_exists($customer, 'resolvePhone')) {
            return null;
        }

        return $this->cleanString(call_user_func([$customer, 'resolvePhone']));
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null || ! is_scalar($value)) {
            return null;
        }

        $cleaned = mb_trim((string) $value);

        return $cleaned === '' ? null : $cleaned;
    }
}
