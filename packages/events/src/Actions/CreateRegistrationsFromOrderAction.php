<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Contracts\EventRegistrationEligibility;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Support\EventRegistrationScope;
use AIArmada\Events\Support\EventTicketScope;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\Ticketing\Enums\PricingMode;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateRegistrationsFromOrderAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly EventRegistrationEligibility $eligibility,
        private readonly CreateEventComponentRegistrationsAction $expandComponents,
        private readonly RegisterForFreeAction $registerForFree,
        private readonly LockEventRegistrationScopeAction $lockScope,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @param  array<string, mixed>  $options
     * @return Collection<int, EventRegistration>
     */
    public function handle(
        Model $target,
        mixed $orderItem,
        array $participants,
        mixed $purchaser = null,
        array $options = [],
    ): Collection {
        $scope = $this->scopeResolver->resolve($target);
        $this->eligibility->ensureEligible($scope);

        $this->resolveWithOwnerGuard($scope->event::class, $scope->event->id);

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

        return DB::transaction(function () use (
            $expectedCount,
            $options,
            $orderClass,
            $orderItem,
            $orderItemClass,
            $participants,
            $purchaser,
            $scope,
            $target,
        ): Collection {
            $orderItem = $orderItemClass::query()
                ->whereKey($orderItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $orderItem->quantity !== $expectedCount) {
                throw new InvalidArgumentException('The selected order item changed while it was being fulfilled. Please retry.');
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

            if ($scope->pricingMode === PricingMode::Free) {
                return $this->createFreeOrderRegistrations(
                    target: $target,
                    orderItem: $orderItem,
                    orderClass: $orderClass,
                    orderItemClass: $orderItemClass,
                    participants: $participants,
                    purchaser: $purchaser,
                    ticketType: $ticketType,
                    options: $options,
                );
            }

            if ($this->capacityEnforcementEnabled($scope)) {
                $this->lockScope->handle($scope);
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
            $registrationStatus = $this->registrationStatus($options);
            $itemStatus = $this->itemStatus($options, $registrationStatus);
            $source = $this->source($options);
            $paymentStatus = $this->paymentStatus($options);
            $registrationMetadata = $this->registrationMetadata($options);
            $registrationNotes = $this->registrationNotes($options);

            $lineTotal = $this->resolveOrderItemLineTotal($orderItem, $expectedCount);

            foreach ($participants as $participantIndex => $participant) {
                $allocatedTotal = $this->allocateLineTotal($lineTotal, $expectedCount, $participantIndex);

                $registration = $this->registrationService->register(array_merge($scopeData, [
                    'registrant_type' => $purchaser instanceof Model ? $purchaser->getMorphClass() : null,
                    'registrant_id' => $purchaser instanceof Model ? $purchaser->getKey() : null,
                    'registration_type' => 'individual',
                    'status' => $registrationStatus,
                    'source' => $source,
                    'total_participants' => 1,
                    'total_amount' => $allocatedTotal,
                    'external_order_id' => $orderItem->order_id,
                    'external_order_type' => $orderClass,
                    'payment_status' => $paymentStatus,
                    'metadata' => $registrationMetadata,
                    'notes' => $registrationNotes,
                    'items' => [[
                        'ticket_type_id' => $ticketType->getKey(),
                        'quantity' => 1,
                        'unit_price' => $orderItem->unit_price,
                        'total_price' => $allocatedTotal,
                        'currency' => $orderItem->currency,
                        'status' => $itemStatus,
                        'external_order_item_id' => $orderItem->getKey(),
                        'external_order_item_type' => $orderItemClass,
                        'metadata' => [
                            'order_item_quantity' => $orderItem->quantity,
                            'order_item_total' => $orderItem->total,
                            'allocated_total' => $allocatedTotal,
                        ],
                    ]],
                    'participants' => [$participant],
                ]));

                $registrations->push($registration);

                $this->expandComponents->handle($registration, options: [
                    'status' => $registrationStatus,
                    'source' => $source,
                    'payment_status' => $paymentStatus,
                    'metadata' => $registrationMetadata,
                ]);
            }

            return $registrations;
        });
    }

    /**
     * Free tickets still travel through the order pipeline. Reusing the
     * free-registration action keeps capacity and eligibility rules identical
     * to direct RSVP, while the order item gives fulfillment and pass issuance
     * a durable commerce link.
     *
     * @param  class-string<Model>  $orderClass
     * @param  class-string<Model>  $orderItemClass
     * @param  array<int, array<string, mixed>>  $participants
     * @param  array<string, mixed>  $options
     * @return Collection<int, EventRegistration>
     */
    private function createFreeOrderRegistrations(
        Model $target,
        mixed $orderItem,
        string $orderClass,
        string $orderItemClass,
        array $participants,
        mixed $purchaser,
        TicketType $ticketType,
        array $options = [],
    ): Collection {
        $registrations = $this->registerForFree->execute(
            target: $target,
            participants: $participants,
            registrant: $purchaser instanceof Model ? $purchaser : null,
            options: ['defer_pass_issuance' => true],
        );

        $registrationMetadata = $this->registrationMetadata($options);
        $registrationNotes = $this->registrationNotes($options);
        $source = $this->source($options);

        foreach ($registrations as $registration) {
            $currency = (string) ($orderItem->currency
                ?? $ticketType->currency
                ?? config('events.defaults.currency', 'MYR'));
            $total = (int) ($orderItem->total ?? $orderItem->unit_price ?? 0);

            $registration->forceFill([
                'source' => $source,
                'total_amount' => $total,
                'currency' => $currency,
                'external_order_id' => $orderItem->order_id,
                'external_order_type' => $orderClass,
                'payment_status' => 'free',
                'metadata' => $registrationMetadata,
                'notes' => $registrationNotes,
            ])->save();

            $registration->items()->create([
                'ticket_type_id' => $ticketType->getKey(),
                'quantity' => 1,
                'unit_price' => (int) ($orderItem->unit_price ?? 0),
                'total_price' => $total,
                'currency' => $currency,
                'status' => 'confirmed',
                'external_order_item_id' => $orderItem->getKey(),
                'external_order_item_type' => $orderItemClass,
                'metadata' => [
                    'order_item_quantity' => $orderItem->quantity,
                    'order_item_total' => $total,
                    'free_ticket_order' => true,
                ],
            ]);

            $registration->load(['participants', 'items.ticketType']);
            $this->expandComponents->handle($registration, options: [
                'status' => 'confirmed',
                'source' => $source,
                'payment_status' => 'free',
                'metadata' => $registrationMetadata,
            ]);
        }

        return $registrations;
    }

    private function ticketTypeBelongsToScope(TicketType $ticketType, EventRegistrationScope $scope): bool
    {
        $ticketType->loadMissing('ticketable');

        return EventTicketScope::belongsToRegistrationScope($ticketType, $scope);
    }

    private function registrationStatus(array $options): string
    {
        $status = $options['registration_status'] ?? 'confirmed';

        if (! is_string($status) || ! in_array($status, ['pending', 'confirmed'], true)) {
            throw new InvalidArgumentException('Order registrations may only start as pending or confirmed.');
        }

        return $status;
    }

    private function itemStatus(array $options, string $registrationStatus): string
    {
        $status = $options['item_status'] ?? $registrationStatus;

        if (! is_string($status) || ! in_array($status, ['pending', 'confirmed'], true)) {
            throw new InvalidArgumentException('Order registration items may only start as pending or confirmed.');
        }

        return $status;
    }

    private function source(array $options): string
    {
        $source = $options['source'] ?? 'order';

        if (! is_string($source) || mb_trim($source) === '') {
            throw new InvalidArgumentException('An order registration source is required.');
        }

        return mb_trim($source);
    }

    private function paymentStatus(array $options): ?string
    {
        $paymentStatus = $options['payment_status'] ?? null;

        if ($paymentStatus === null) {
            return null;
        }

        if (! is_string($paymentStatus) || mb_trim($paymentStatus) === '') {
            throw new InvalidArgumentException('The order registration payment status must be a non-empty string.');
        }

        return mb_trim($paymentStatus);
    }

    /** @return array<string, mixed>|null */
    private function registrationMetadata(array $options): ?array
    {
        $metadata = $options['metadata'] ?? null;

        return is_array($metadata) ? $metadata : null;
    }

    private function registrationNotes(array $options): ?string
    {
        $notes = $options['notes'] ?? null;

        return is_string($notes) && mb_trim($notes) !== '' ? mb_trim($notes) : null;
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
        $registrationClass = ModelResolver::registrationClass();

        $query = $registrationClass::query()
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
        if (! $this->capacityEnforcementEnabled($scope)) {
            return false;
        }

        $remaining = $scope->capacityRemaining();

        if ($remaining === null) {
            return false;
        }

        return $expectedCount > $remaining;
    }

    private function capacityEnforcementEnabled(EventRegistrationScope $scope): bool
    {
        return (bool) config('events.features.enforce_scope_capacity_on_paid_registrations', false)
            && $scope->capacity !== null;
    }

    private function resolveOrderItemLineTotal(mixed $orderItem, int $quantity): int
    {
        $total = (int) ($orderItem->total ?? 0);

        if ($total > 0) {
            return $total;
        }

        // Some order writers leave total at its model default of zero. Keep a
        // real zero for fully discounted lines, otherwise derive the line
        // amount from the immutable order-item pricing snapshot.
        $discount = (int) ($orderItem->discount_amount ?? 0);

        if ($discount > 0) {
            return 0;
        }

        return max(0, ((int) ($orderItem->unit_price ?? 0) * $quantity) + (int) ($orderItem->tax_amount ?? 0));
    }

    private function allocateLineTotal(int $lineTotal, int $quantity, int $index): int
    {
        if ($quantity < 1) {
            return 0;
        }

        $base = intdiv($lineTotal, $quantity);
        $remainder = $lineTotal % $quantity;

        return $base + ($index < $remainder ? 1 : 0);
    }
}
