<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class CreateRegistrationsForOrderItemAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @return Collection<int, EventRegistration>
     */
    public function handle(EventOccurrence $occurrence, mixed $orderItem, array $participants, mixed $purchaser = null): Collection
    {
        OwnerWriteGuard::findOrFailForOwner(Event::class, $occurrence->event_id);

        /** @var class-string<Model> $orderClass */
        $orderClass = CommerceIntegration::requireModelClass('order_model', 'order fulfillment');
        $orderItemClass = CommerceIntegration::requireModelClass('order_item_model', 'order item fulfillment');

        if (! $orderItem instanceof $orderItemClass) {
            throw new InvalidArgumentException(sprintf('The order item must be an instance of %s.', $orderItemClass));
        }

        if ((int) $orderItem->quantity < 1) {
            throw new InvalidArgumentException('The selected order item must have a positive quantity.');
        }

        $expectedCount = (int) $orderItem->quantity;

        if (count($participants) !== $expectedCount) {
            throw new InvalidArgumentException(sprintf(
                'Expected %d participants for order item %s, received %d.',
                $expectedCount,
                (string) $orderItem->getKey(),
                count($participants),
            ));
        }

        $orderItem->loadMissing('order', 'purchasable');

        if ($orderItem->order_id === null) {
            throw new InvalidArgumentException('The selected order item must belong to an order.');
        }

        OwnerWriteGuard::findOrFailForOwner($orderClass, $orderItem->order_id);

        if (! $orderItem->order instanceof Model || ! is_a($orderItem->order, $orderClass, true)) {
            throw new InvalidArgumentException(sprintf('The order item must belong to an instance of %s.', $orderClass));
        }

        $ticketType = $orderItem->purchasable;

        if (
            ! $ticketType instanceof EventTicketType
            || $ticketType->event_id !== $occurrence->event_id
            || ($ticketType->event_occurrence_id !== null && $ticketType->event_occurrence_id !== $occurrence->id)
        ) {
            throw new InvalidArgumentException('The selected order item must reference a ticket type that belongs to the same event occurrence.');
        }

        $existing = EventRegistration::query()
            ->where('event_occurrence_id', $occurrence->id)
            ->whereHas('items', function (Builder $query) use ($orderItem, $orderItemClass): void {
                $query
                    ->where('external_order_item_id', $orderItem->getKey())
                    ->where('external_order_item_type', $orderItemClass);
            })
            ->get();

        if ($existing->isNotEmpty()) {
            if ($existing->count() !== $expectedCount) {
                throw new InvalidArgumentException(sprintf(
                    'Expected %d existing registrations for order item %s, found %d.',
                    $expectedCount,
                    (string) $orderItem->getKey(),
                    $existing->count(),
                ));
            }

            return $existing;
        }

        $registrations = new Collection;
        foreach ($participants as $participant) {
            $registrations->push(
                $this->registrationService->register([
                    'event_id' => $occurrence->event_id,
                    'event_occurrence_id' => $occurrence->id,
                    'registrant_type' => $purchaser instanceof Model ? $purchaser->getMorphClass() : null,
                    'registrant_id' => $purchaser instanceof Model ? $purchaser->getKey() : null,
                    'registration_type' => 'individual',
                    'status' => 'confirmed',
                    'source' => 'order',
                    'total_participants' => 1,
                    'external_order_id' => $orderItem->order_id,
                    'external_order_type' => $orderClass,
                    'items' => [[
                        'event_ticket_type_id' => $ticketType->getKey(),
                        'quantity' => 1,
                        'unit_price' => $orderItem->unit_price,
                        'total_price' => $orderItem->unit_price,
                        'currency' => $orderItem->currency,
                        'status' => 'confirmed',
                        'external_order_item_id' => $orderItem->getKey(),
                        'external_order_item_type' => $orderItemClass,
                        'metadata' => [
                            'order_item_quantity' => $orderItem->quantity,
                            'order_item_total' => $orderItem->total,
                        ],
                    ]],
                    'participants' => [$participant],
                ])
            );
        }

        return $registrations;
    }
}
