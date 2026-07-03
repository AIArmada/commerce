<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Enums\PricingMode;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Exceptions\EventIsFreeException;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Support\EventRegistrationScope;
use AIArmada\Events\Support\EventTicketScope;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class CreateRegistrationsFromOrderAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly ExpandTicketTypeComponentsAction $expandComponents,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @return Collection<int, EventRegistration>
     */
    public function handle(Model $target, mixed $orderItem, array $participants, mixed $purchaser = null): Collection
    {
        $scope = $this->scopeResolver->resolve($target);

        $this->resolveWithOwnerGuard($scope->event::class, $scope->event->id);

        if ($scope->pricingMode === PricingMode::Free) {
            throw new EventIsFreeException(
                sprintf('Event %s is free; use RegisterForFreeAction instead.', $scope->event->id),
            );
        }

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

        $this->resolveWithOwnerGuard($orderClass, $orderItem->order_id);

        if (! $orderItem->order instanceof Model || ! is_a($orderItem->order, $orderClass, true)) {
            throw new InvalidArgumentException(sprintf('The order item must belong to an instance of %s.', $orderClass));
        }

        $ticketType = $orderItem->purchasable;

        if (! $ticketType instanceof TicketType) {
            throw new InvalidArgumentException('The selected order item must reference a TicketType.');
        }

        if (! $this->ticketTypeBelongsToScope($ticketType, $scope)) {
            throw new InvalidArgumentException('The selected order item must reference a ticket type that belongs to the same event scope.');
        }

        $existing = $this->findExistingRegistrations($scope, $orderItem, $orderItemClass, $expectedCount);

        if ($existing !== null) {
            return $existing;
        }

        if ($this->shouldEnforceCapacity($expectedCount, $scope)) {
            throw new EventCapacityExceededException(
                sprintf(
                    'The event scope does not have enough capacity for %d registrations.',
                    $expectedCount,
                ),
            );
        }

        $registrations = new Collection;
        $scopeData = $scope->toRegistrationData();

        foreach ($participants as $participant) {
            $registration = $this->registrationService->register(array_merge($scopeData, [
                'registrant_type' => $purchaser instanceof Model ? $purchaser->getMorphClass() : null,
                'registrant_id' => $purchaser instanceof Model ? $purchaser->getKey() : null,
                'registration_type' => 'individual',
                'status' => 'confirmed',
                'source' => 'order',
                'total_participants' => 1,
                'external_order_id' => $orderItem->order_id,
                'external_order_type' => $orderClass,
                'items' => [[
                    'ticket_type_id' => $ticketType->getKey(),
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
            ]));

            $registrations->push($registration);

            $this->expandComponents->handle($registration);
        }

        return $registrations;
    }

    private function ticketTypeBelongsToScope(TicketType $ticketType, EventRegistrationScope $scope): bool
    {
        $ticketType->loadMissing('ticketable');

        return EventTicketScope::belongsToRegistrationScope($ticketType, $scope);
    }

    /**
     * @param  class-string<Model>  $orderItemClass
     * @return Collection<int, EventRegistration>|null
     */
    private function findExistingRegistrations(
        EventRegistrationScope $scope,
        mixed $orderItem,
        string $orderItemClass,
        int $expectedCount,
    ): ?Collection {
        $query = EventRegistration::query()
            ->where('event_id', $scope->event->id);

        if ($scope->occurrence !== null) {
            $query->where('event_occurrence_id', $scope->occurrence->id);
        }

        if ($scope->session !== null) {
            $query->where('event_session_id', $scope->session->id);
        }

        $existing = $query
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

        return null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function resolveWithOwnerGuard(string $modelClass, int | string $id): void
    {
        if (method_exists($modelClass, 'ownerScopeConfig') && ! $modelClass::ownerScopeConfig()->enabled) {
            $modelClass::query()->findOrFail($id);

            return;
        }

        OwnerWriteGuard::findOrFailForOwner($modelClass, $id);
    }

    private function shouldEnforceCapacity(int $expectedCount, EventRegistrationScope $scope): bool
    {
        if (! config('events.features.enforce_scope_capacity_on_paid_registrations', false)) {
            return false;
        }

        $remaining = $scope->capacityRemaining();

        if ($remaining === null) {
            return false;
        }

        return $expectedCount > $remaining;
    }
}
