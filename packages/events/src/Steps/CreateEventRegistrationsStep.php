<?php

declare(strict_types=1);

namespace AIArmada\Events\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\AbstractCheckoutStep;
use AIArmada\Events\Actions\CreateRegistrationsFromOrderAction;
use AIArmada\Events\Support\EventTicketScope;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;

final class CreateEventRegistrationsStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly CreateRegistrationsFromOrderAction $createRegistrations,
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

            if (! $purchasable instanceof TicketType || EventTicketScope::target($purchasable) === null) {
                continue;
            }

            $ticketType = $purchasable;

            $this->markEventFulfillment($orderItem);

            $target = $this->resolveRegistrationTarget($ticketType);

            if ($target === null) {
                continue;
            }

            $participants = $this->resolveParticipants(
                session: $session,
                orderItem: $orderItem,
                cartItems: $cartItems,
                order: $order,
            );

            $this->createRegistrations->handle(
                $target,
                $orderItem,
                $participants,
                $this->resolveRegistrant($session, $order),
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

    private function markEventFulfillment(mixed $orderItem): void
    {
        $options = $orderItem->getAttribute('options');

        $options = is_array($options) ? $options : [];

        if (($options['event_fulfillment'] ?? null) === 'event_registration') {
            return;
        }

        $orderItem->forceFill([
            'options' => [...$options, 'event_fulfillment' => 'event_registration'],
        ])->save();
    }

    private function resolveCustomerEmail(mixed $customer): ?string
    {
        if (! $customer instanceof Model || ! method_exists($customer, 'contactMethods')) {
            return null;
        }

        $email = $this->cleanString($customer->getAttribute('email'));

        if ($email !== null) {
            return mb_strtolower($email);
        }

        $contactMethods = call_user_func([$customer, 'contactMethods']);

        if (! $contactMethods instanceof MorphMany) {
            return null;
        }

        $emailContactMethod = $contactMethods
            ->where('type', 'email')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->first();

        return $this->cleanString(
            $emailContactMethod?->getAttribute('normalized_value')
                ?? $emailContactMethod?->getAttribute('value'),
        );
    }

    private function resolveCustomerPhone(mixed $customer): ?string
    {
        if (! $customer instanceof Model || ! method_exists($customer, 'contactMethods')) {
            return null;
        }

        $phone = $this->cleanString($customer->getAttribute('phone'));

        if ($phone !== null) {
            return $phone;
        }

        $contactMethods = call_user_func([$customer, 'contactMethods']);

        if (! $contactMethods instanceof MorphMany) {
            return null;
        }

        $phoneContactMethod = $contactMethods
            ->where('type', 'phone')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->first();

        return $this->cleanString(
            $phoneContactMethod?->getAttribute('normalized_value')
                ?? $phoneContactMethod?->getAttribute('value'),
        );
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
