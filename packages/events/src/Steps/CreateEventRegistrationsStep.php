<?php

declare(strict_types=1);

namespace AIArmada\Events\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\AbstractCheckoutStep;
use AIArmada\Events\Actions\CreateRegistrationsFromOrderAction;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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

            if (! $purchasable instanceof EventTicketType) {
                continue;
            }

            $ticketType = $purchasable;

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
                $order->getRelation('customer'),
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

    private function resolveRegistrationTarget(EventTicketType $ticketType): ?Model
    {
        $ticketType->loadMissing('event', 'occurrence', 'session');

        if ($ticketType->event_session_id !== null) {
            return $ticketType->session;
        }

        if ($ticketType->event_occurrence_id !== null) {
            return $ticketType->occurrence;
        }

        return $ticketType->event;
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
