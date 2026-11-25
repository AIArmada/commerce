<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Contracts\EventRegistrationEligibility;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Data\EventRegistrationScopeData;
use AIArmada\Events\Data\OpenDoorMode;
use AIArmada\Events\Events\EventFreeRegistrationConfirmed;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Exceptions\NotFreeEventException;
use AIArmada\Events\Exceptions\OpenDoorRegistrationBlockedException;
use AIArmada\Events\Exceptions\UseRecordHeadcountActionException;
use AIArmada\Events\Exceptions\UseRecordWalkInActionException;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\Queries\RegistrationCapacityQuery;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Ticketing\Enums\PricingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class RegisterForFreeAction
{
    public function __construct(
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly EventRegistrationEligibility $eligibility,
        private readonly RegistrationServiceInterface $registrations,
        private readonly LockEventRegistrationScopeAction $lockScope,
        private readonly RegistrationCapacityQuery $capacity,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    public function execute(
        string $scopeType,
        string $scopeId,
        array $participants,
        ?string $registrantType = null,
        ?string $registrantId = null,
        array $options = [],
    ): array {
        $scope = $this->scopeResolver->resolve($scopeType, $scopeId);
        $this->eligibility->ensureEligible($scope);
        EventWriteGuard::findOrFail($scope->eventId);

        if (! $scope->isFreeOnly() && $scope->pricingMode !== PricingMode::Mixed) {
            throw new NotFreeEventException(
                "Event {$scope->eventId} is not free. Use the paid path or set pricing_mode to 'free' or 'mixed'.",
            );
        }

        if ($scope->isOpenDoor()) {
            $this->throwOpenDoorException();
        }

        // A free ticket purchased through checkout is confirmed now, while
        // pass issuance is deferred to the checkout fulfillment step. This
        // prevents the same admission pass from being issued twice.
        $deferPassIssuance = (bool) ($options['defer_pass_issuance'] ?? false);
        $withPass = $deferPassIssuance
            ? false
            : ($scope->requiresRegistration()
                ? true
                : (bool) ($options['with_pass'] ?? $scope->shouldIssuePasses));

        $status = ($withPass || $deferPassIssuance) ? 'confirmed' : 'interested';
        $source = $this->source($options, $deferPassIssuance, $withPass);
        $idempotencyKey = $this->idempotencyKey($options);

        $result = DB::transaction(function () use (
            $idempotencyKey,
            $options,
            $participants,
            $registrantId,
            $registrantType,
            $scope,
            $source,
            $status,
        ): array {
            $this->lockScope->handle($scope);

            $existing = $this->findIdempotentRegistrations($scope, $idempotencyKey, count($participants));

            if ($existing !== null) {
                return ['ids' => $existing, 'created' => false];
            }

            $this->checkCapacity($scope, count($participants));

            $ids = [];

            foreach ($participants as $participant) {
                $ids[] = $this->registrations->register($this->registrationPayload(
                    scope: $scope,
                    participant: $participant,
                    options: $options,
                    status: $status,
                    source: $source,
                    idempotencyKey: $idempotencyKey,
                    registrantType: $registrantType,
                    registrantId: $registrantId,
                ));
            }

            return ['ids' => $ids, 'created' => true];
        });

        if ($result['created']) {
            foreach ($result['ids'] as $registrationId) {
                event(new EventFreeRegistrationConfirmed($registrationId, $scope->eventId, $withPass));
            }
        }

        return $result['ids'];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function source(array $options, bool $deferPassIssuance, bool $withPass): string
    {
        $configured = $options['source'] ?? null;

        if (is_string($configured) && mb_trim($configured) !== '') {
            return mb_trim($configured);
        }

        return $deferPassIssuance
            ? 'order'
            : ($withPass ? 'free_rsvp' : 'free_optional_rsvp');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function idempotencyKey(array $options): ?string
    {
        $key = $options['idempotency_key'] ?? null;

        if ($key === null) {
            return null;
        }

        if (! is_string($key) || mb_trim($key) === '') {
            throw new InvalidArgumentException('The registration idempotency key must be a non-empty string.');
        }

        $key = mb_trim($key);

        if (mb_strlen($key) > 255) {
            throw new InvalidArgumentException('The registration idempotency key may not exceed 255 characters.');
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $participant
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function registrationPayload(
        EventRegistrationScopeData $scope,
        array $participant,
        array $options,
        string $status,
        string $source,
        ?string $idempotencyKey,
        ?string $registrantType,
        ?string $registrantId,
    ): array {
        $payload = array_merge($scope->toRegistrationData(), [
            'registrant_type' => $registrantType,
            'registrant_id' => $registrantId,
            'registration_type' => 'individual',
            'status' => $status,
            'source' => $source,
            'total_participants' => 1,
            'total_amount' => $options['total_amount'] ?? null,
            'currency' => $options['currency'] ?? null,
            'payment_status' => $options['payment_status'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'metadata' => is_array($options['metadata'] ?? null) ? $options['metadata'] : null,
            'notes' => is_string($options['notes'] ?? null) ? $options['notes'] : null,
            'participants' => [$participant],
        ]);

        if (($options['external_order_id'] ?? null) !== null) {
            $payload['external_order_id'] = $options['external_order_id'];
            $payload['external_order_type'] = $options['external_order_type'] ?? null;
        }

        if (is_array($options['item'] ?? null)) {
            $payload['items'] = [$options['item']];
        }

        return $payload;
    }

    /**
     * @return list<string>|null
     */
    private function findIdempotentRegistrations(
        EventRegistrationScopeData $scope,
        ?string $idempotencyKey,
        int $expectedCount,
    ): ?array {
        if ($idempotencyKey === null) {
            return null;
        }

        $query = EventRegistration::query()->where('event_id', $scope->eventId);

        $this->applyScopeColumns($query, $scope);

        $existing = $query
            ->where('idempotency_key', $idempotencyKey)
            ->limit($expectedCount + 1)
            ->get()
            ->values();

        if ($existing->isEmpty()) {
            return null;
        }

        if ($existing->count() !== $expectedCount) {
            throw new RuntimeException('The idempotency key is already associated with an incomplete registration batch.');
        }

        return $existing
            ->map(static fn (EventRegistration $registration): string => (string) $registration->getKey())
            ->all();
    }

    private function checkCapacity(EventRegistrationScopeData $scope, int $participantCount): void
    {
        $remaining = $this->capacityRemaining($scope);

        if ($remaining !== null && $participantCount > $remaining) {
            throw new EventCapacityExceededException(
                sprintf(
                    'Capacity exceeded: %d remaining, %d requested.',
                    $remaining,
                    $participantCount,
                ),
            );
        }
    }

    private function capacityRemaining(EventRegistrationScopeData $scope): ?int
    {
        return $this->capacity->remaining(
            eventId: $scope->eventId,
            occurrenceId: $scope->occurrenceId,
            sessionId: $scope->sessionId,
            capacity: $scope->capacity,
        );
    }

    private function applyScopeColumns(Builder $query, EventRegistrationScopeData $scope): void
    {
        if ($scope->occurrenceId !== null) {
            $query->where('event_occurrence_id', $scope->occurrenceId);
        } else {
            $query->whereNull('event_occurrence_id');
        }

        if ($scope->sessionId !== null) {
            $query->where('event_session_id', $scope->sessionId);
        } else {
            $query->whereNull('event_session_id');
        }
    }

    private function throwOpenDoorException(): never
    {
        $mode = OpenDoorMode::tryFrom(
            config('events.features.free_only.open_door_mode', 'block'),
        ) ?? OpenDoorMode::Block;

        match ($mode) {
            OpenDoorMode::Block => throw new OpenDoorRegistrationBlockedException(
                'Open door event: public registration is not available.',
            ),
            OpenDoorMode::WalkIn => throw new UseRecordWalkInActionException(
                'Use RecordWalkInAction for open-door events.',
            ),
            OpenDoorMode::Headcount => throw new UseRecordHeadcountActionException(
                'Use RecordHeadcountLogAction for open-door events.',
            ),
        };
    }
}
