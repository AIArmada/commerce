<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Data\EventOrderItemFulfillment;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final class DefaultEventOrderItemFulfillmentResolver implements EventOrderItemFulfillmentResolver
{
    public function resolve(Order $order, OrderItem $orderItem): ?EventOrderItemFulfillment
    {
        $options = is_array($orderItem->options) ? $orderItem->options : [];
        $checkoutMetadata = Arr::get($options, 'checkout_metadata');

        if (! is_array($checkoutMetadata)) {
            return null;
        }

        $occurrenceId = $this->stringValue(Arr::get($checkoutMetadata, 'occurrence_id') ?? Arr::get($options, 'occurrence_id'));

        if ($occurrenceId === null) {
            return null;
        }

        $owner = OwnerContext::fromTypeAndId($order->owner_type, $order->owner_id);

        return OwnerContext::withOwner($owner, function () use ($order, $orderItem, $checkoutMetadata, $occurrenceId): ?EventOrderItemFulfillment {
            $occurrence = Occurrence::query()->find($occurrenceId);

            if (! $occurrence instanceof Occurrence || ! $occurrence->isPaidRegistration()) {
                return null;
            }

            $participants = $this->resolveParticipants($order, $orderItem, $checkoutMetadata);
            $purchaser = $this->resolvePurchaser($order);

            if ($participants === []) {
                return null;
            }

            return new EventOrderItemFulfillment(
                occurrence: $occurrence,
                participants: $participants,
                purchaser: $purchaser,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $checkoutMetadata
     * @return array<int, array<string, mixed>>
     */
    private function resolveParticipants(Order $order, OrderItem $orderItem, array $checkoutMetadata): array
    {
        $participants = $this->normalizeParticipantList(Arr::get($checkoutMetadata, 'participants'));

        if ($participants !== []) {
            return $participants;
        }

        $participant = Arr::get($checkoutMetadata, 'participant');

        if (is_array($participant) && $participant !== []) {
            return [$participant];
        }

        $purchaser = $this->resolvePurchaser($order);

        if ($orderItem->quantity === 1 && $purchaser instanceof Customer) {
            return [[
                'name' => mb_trim($purchaser->full_name),
                'email' => $purchaser->email,
                'phone' => $purchaser->phone,
                'company' => $purchaser->company,
            ]];
        }

        throw new InvalidArgumentException(
            'Paid event order items require participant metadata or a single-seat purchaser fallback.',
        );
    }

    private function resolvePurchaser(Order $order): ?Customer
    {
        $order->loadMissing(['customer']);

        if ($order->customer instanceof Customer) {
            return $order->customer;
        }

        $customerId = is_scalar($order->getAttribute('customer_id')) ? (string) $order->getAttribute('customer_id') : null;

        if ($customerId === null || $customerId === '') {
            return null;
        }

        $customer = Customer::query()
            ->withoutOwnerScope()
            ->find($customerId);

        return $customer instanceof Customer ? $customer : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeParticipantList(mixed $participants): array
    {
        if (! is_array($participants) || $participants === []) {
            return [];
        }

        if (array_is_list($participants)) {
            return array_values(array_filter(
                $participants,
                static fn (mixed $participant): bool => is_array($participant) && $participant !== [],
            ));
        }

        if ($this->looksLikeParticipant($participants)) {
            return [$participants];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $participant
     */
    private function looksLikeParticipant(array $participant): bool
    {
        return array_key_exists('name', $participant)
            || array_key_exists('email', $participant)
            || array_key_exists('first_name', $participant)
            || array_key_exists('attendee', $participant);
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = mb_trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
