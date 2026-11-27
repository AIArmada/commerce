<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Contracts\EventOrderDriver;
use AIArmada\Events\Contracts\EventRegistrationEligibility;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Data\EventOrderItemData;
use AIArmada\Events\Data\EventRegistrationScopeData;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Platform\EventTicketScope;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Ticketing\Enums\PricingMode;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateRegistrationsFromOrderAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly EventRegistrationEligibility $eligibility,
        private readonly RegistrationsApi $registrationsApi,
        private readonly EventOrderDriver $orders,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    public function handle(
        string $scopeType,
        string $scopeId,
        string $orderItemId,
        array $participants,
        ?Model $purchaser = null,
        array $options = [],
    ): array {
        $scope = $this->scopeResolver->resolve($scopeType, $scopeId);
        $this->eligibility->ensureEligible($scope);

        EventWriteGuard::findOrFail($scope->eventId);

        $orderItem = $this->orders->orderItemSnapshot($orderItemId) ?? throw new InvalidArgumentException('The selected order item does not exist.');

        if ($orderItem->quantity < 1) {
            throw new InvalidArgumentException('The selected order item must have a positive quantity.');
        }

        $expectedCount = $orderItem->quantity;

        if (count($participants) !== $expectedCount) {
            throw new InvalidArgumentException(sprintf(
                'Expected %d participants for order item %s, received %d.',
                $expectedCount,
                $orderItemId,
                count($participants),
            ));
        }

        $this->orders->assertOrderVisible($orderItem->orderId);

        return DB::transaction(function () use (
            $expectedCount,
            $options,
            $orderItemId,
            $participants,
            $purchaser,
            $scope,
            $scopeType,
            $scopeId,
        ): array {
            $fresh = $this->orders->orderItemSnapshot($orderItemId)
                ?? throw new InvalidArgumentException('The selected order item does not exist.');

            if ($fresh->quantity !== $expectedCount) {
                throw new InvalidArgumentException('The selected order item changed while it was being fulfilled. Please retry.');
            }

            $ticketType = $fresh->ticketTypeId !== null
                ? TicketType::query()->find($fresh->ticketTypeId)
                : null;

            if (! $ticketType instanceof TicketType) {
                throw new InvalidArgumentException('The selected order item must reference a TicketType.');
            }

            $ticketType->loadMissing('ticketable');

            if (! EventTicketScope::belongsToRegistrationScope($ticketType, $scope)) {
                throw new InvalidArgumentException('The selected order item must reference a ticket type that belongs to the same event scope.');
            }

            $this->registrationsApi->lockScope($scopeType, $scopeId);

            $existing = $this->findExistingRegistrations($scope, $fresh, $expectedCount);

            if ($existing !== null) {
                return $existing;
            }

            if ($scope->pricingMode === PricingMode::Free || $this->isMixedScopeFreeTicket($scope, $ticketType)) {
                return $this->createFreeOrderRegistrations(
                    scope: $scope,
                    scopeType: $scopeType,
                    scopeId: $scopeId,
                    orderItem: $fresh,
                    ticketType: $ticketType,
                    participants: $participants,
                    purchaser: $purchaser,
                    options: $options,
                );
            }

            if ($this->shouldEnforceCapacity($expectedCount, $scope)) {
                throw new EventCapacityExceededException(
                    sprintf(
                        'The event scope does not have enough capacity for %d registrations.',
                        $expectedCount,
                    ),
                );
            }

            $scopeData = $scope->toRegistrationData();
            $registrationStatus = $this->registrationStatus($options);
            $itemStatus = $this->itemStatus($options, $registrationStatus);
            $source = $this->source($options);
            $paymentStatus = $this->paymentStatus($options);
            $registrationMetadata = $this->registrationMetadata($options);
            $registrationNotes = $this->registrationNotes($options);

            $lineTotal = $this->resolveOrderItemLineTotal($fresh, $expectedCount);
            $currency = (string) ($fresh->currency
                ?? $ticketType->currency
                ?? config('events.defaults.currency', 'MYR'));

            $ids = [];

            foreach ($participants as $participantIndex => $participant) {
                $allocatedTotal = $this->allocateLineTotal($lineTotal, $expectedCount, $participantIndex);

                $registrationId = $this->registrationService->register(array_merge($scopeData, [
                    'registrant_type' => $purchaser instanceof Model ? $purchaser->getMorphClass() : null,
                    'registrant_id' => $purchaser instanceof Model ? $purchaser->getKey() : null,
                    'registration_type' => 'individual',
                    'status' => $registrationStatus,
                    'source' => $source,
                    'total_participants' => 1,
                    'total_amount' => $allocatedTotal,
                    'currency' => $currency,
                    'external_order_id' => $fresh->orderId,
                    'external_order_type' => $fresh->orderClass,
                    'payment_status' => $paymentStatus,
                    'metadata' => $registrationMetadata,
                    'notes' => $registrationNotes,
                    'items' => [[
                        'ticket_type_id' => $ticketType->getKey(),
                        'quantity' => 1,
                        'unit_price' => $fresh->unitPrice,
                        'total_price' => $allocatedTotal,
                        'currency' => $currency,
                        'status' => $itemStatus,
                        'external_order_item_id' => $fresh->id,
                        'external_order_item_type' => $fresh->orderItemClass,
                        'metadata' => [
                            'order_item_quantity' => $fresh->quantity,
                            'order_item_total' => $fresh->total,
                            'allocated_total' => $allocatedTotal,
                        ],
                    ]],
                    'participants' => [$participant],
                ]));

                $ids[] = $registrationId;

                $this->registrationsApi->expandComponents($registrationId, [
                    'status' => $registrationStatus,
                    'source' => $source,
                    'payment_status' => $paymentStatus,
                    'metadata' => $registrationMetadata,
                ]);
            }

            return $ids;
        });
    }

    /**
     * Free tickets still travel through the order pipeline. Reusing the
     * free-registration flow keeps capacity and eligibility rules identical
     * to direct RSVP, while the order item gives fulfillment and pass issuance
     * a durable commerce link.
     *
     * @param  array<int, array<string, mixed>>  $participants
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function createFreeOrderRegistrations(
        EventRegistrationScopeData $scope,
        string $scopeType,
        string $scopeId,
        EventOrderItemData $orderItem,
        TicketType $ticketType,
        array $participants,
        ?Model $purchaser,
        array $options,
    ): array {
        $currency = (string) ($orderItem->currency
            ?? $ticketType->currency
            ?? config('events.defaults.currency', 'MYR'));
        $total = $orderItem->total;
        $source = $this->source($options);
        $registrationMetadata = $this->registrationMetadata($options);
        $registrationNotes = $this->registrationNotes($options);

        $ids = $this->registrationsApi->registerForFree(
            scopeType: $scopeType,
            scopeId: $scopeId,
            participants: $participants,
            registrantType: $purchaser instanceof Model ? $purchaser->getMorphClass() : null,
            registrantId: $purchaser instanceof Model ? (string) $purchaser->getKey() : null,
            options: [
                'defer_pass_issuance' => true,
                'source' => $source,
                'total_amount' => $total,
                'currency' => $currency,
                'payment_status' => 'free',
                'metadata' => $registrationMetadata,
                'notes' => $registrationNotes,
                'external_order_id' => $orderItem->orderId,
                'external_order_type' => $orderItem->orderClass,
                'item' => [
                    'ticket_type_id' => $ticketType->getKey(),
                    'quantity' => 1,
                    'unit_price' => (int) $orderItem->unitPrice,
                    'total_price' => $total,
                    'currency' => $currency,
                    'status' => 'confirmed',
                    'external_order_item_id' => $orderItem->id,
                    'external_order_item_type' => $orderItem->orderItemClass,
                    'metadata' => [
                        'order_item_quantity' => $orderItem->quantity,
                        'order_item_total' => $total,
                        'free_ticket_order' => true,
                    ],
                ],
            ],
        );

        foreach ($ids as $registrationId) {
            $this->registrationsApi->expandComponents($registrationId, [
                'status' => 'confirmed',
                'source' => $source,
                'payment_status' => 'free',
                'metadata' => $registrationMetadata,
            ]);
        }

        return $ids;
    }

    /**
     * A genuinely free ticket stays on the canonical free path even when
     * sibling paid tickets make the scope Mixed. Genuineness comes from the
     * ticket catalog price, never from the order line total: a paid line
     * discounted to zero keeps paid semantics (flag-gated capacity), while a
     * zero-price ticket always enforces scope capacity.
     */
    private function isMixedScopeFreeTicket(EventRegistrationScopeData $scope, TicketType $ticketType): bool
    {
        return $scope->pricingMode === PricingMode::Mixed
            && $ticketType->effectivePricingMode() === PricingMode::Free;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function registrationStatus(array $options): string
    {
        $status = $options['registration_status'] ?? 'confirmed';

        if (! is_string($status) || ! in_array($status, ['pending', 'confirmed'], true)) {
            throw new InvalidArgumentException('Order registrations may only start as pending or confirmed.');
        }

        return $status;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function itemStatus(array $options, string $registrationStatus): string
    {
        $status = $options['item_status'] ?? $registrationStatus;

        if (! is_string($status) || ! in_array($status, ['pending', 'confirmed'], true)) {
            throw new InvalidArgumentException('Order registration items may only start as pending or confirmed.');
        }

        return $status;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function source(array $options): string
    {
        $source = $options['source'] ?? 'order';

        if (! is_string($source) || mb_trim($source) === '') {
            throw new InvalidArgumentException('An order registration source is required.');
        }

        return mb_trim($source);
    }

    /**
     * @param  array<string, mixed>  $options
     */
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

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|null
     */
    private function registrationMetadata(array $options): ?array
    {
        $metadata = $options['metadata'] ?? null;

        return is_array($metadata) ? $metadata : null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function registrationNotes(array $options): ?string
    {
        $notes = $options['notes'] ?? null;

        return is_string($notes) && mb_trim($notes) !== '' ? mb_trim($notes) : null;
    }

    /**
     * @return list<string>|null
     */
    private function findExistingRegistrations(
        EventRegistrationScopeData $scope,
        EventOrderItemData $orderItem,
        int $expectedCount,
    ): ?array {
        $existing = $this->registrationsApi->registrationIdsForOrderItem(
            eventId: $scope->eventId,
            occurrenceId: $scope->occurrenceId,
            sessionId: $scope->sessionId,
            orderItemId: $orderItem->id,
            orderItemType: $orderItem->orderItemClass,
        );

        if ($existing === []) {
            return null;
        }

        if (count($existing) !== $expectedCount) {
            throw new InvalidArgumentException(sprintf(
                'Expected %d existing registrations for order item %s, found %d.',
                $expectedCount,
                $orderItem->id,
                count($existing),
            ));
        }

        return $existing;
    }

    private function shouldEnforceCapacity(int $expectedCount, EventRegistrationScopeData $scope): bool
    {
        if (! $this->capacityEnforcementEnabled($scope)) {
            return false;
        }

        $remaining = $this->registrationsApi->capacityRemaining(
            eventId: $scope->eventId,
            occurrenceId: $scope->occurrenceId,
            sessionId: $scope->sessionId,
            capacity: $scope->capacity,
        );

        if ($remaining === null) {
            return false;
        }

        return $expectedCount > $remaining;
    }

    private function capacityEnforcementEnabled(EventRegistrationScopeData $scope): bool
    {
        return (bool) config('events.features.enforce_scope_capacity_on_paid_registrations', false)
            && $scope->capacity !== null;
    }

    private function resolveOrderItemLineTotal(EventOrderItemData $orderItem, int $quantity): int
    {
        $total = $orderItem->total;

        if ($total > 0) {
            return $total;
        }

        // Some order writers leave total at its model default of zero. Keep a
        // real zero for fully discounted lines, otherwise derive the line
        // amount from the immutable order-item pricing snapshot.
        if ($orderItem->discountAmount > 0) {
            return 0;
        }

        return max(0, ($orderItem->unitPrice * $quantity) + $orderItem->taxAmount);
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
