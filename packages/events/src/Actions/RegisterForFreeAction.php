<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\EventRegistrationEligibility;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Enums\OpenDoorMode;
use AIArmada\Events\Events\EventFreeRegistrationConfirmed;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Exceptions\NotFreeEventException;
use AIArmada\Events\Exceptions\OpenDoorRegistrationBlockedException;
use AIArmada\Events\Exceptions\UseRecordHeadcountActionException;
use AIArmada\Events\Exceptions\UseRecordWalkInActionException;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\States\RegistrationStatus\Confirmed;
use AIArmada\Events\States\RegistrationStatus\Interested;
use AIArmada\Events\Support\EventRegistrationScope;
use AIArmada\Events\Support\EventWriteGuard;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\Ticketing\Enums\PricingMode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @param  array<string, mixed>  $options
     * @return Collection<int, EventRegistration>
     */
    public function execute(
        Model $target,
        array $participants,
        ?Model $registrant = null,
        array $options = [],
    ): Collection {
        $scope = $this->scopeResolver->resolve($target);
        $this->eligibility->ensureEligible($scope);
        EventWriteGuard::findOrFail($scope->event);

        if (! $scope->isFreeOnly() && $scope->pricingMode !== PricingMode::Mixed) {
            throw new NotFreeEventException(
                "Event {$scope->event->id} is not free. Use the paid path or set pricing_mode to 'free' or 'mixed'.",
            );
        }

        if ($scope->isOpenDoor()) {
            $this->throwOpenDoorException($scope);
        }

        // A free ticket purchased through checkout is confirmed now, while
        // pass issuance is deferred to the checkout fulfillment step. This
        // prevents the same admission pass from being issued twice.
        $deferPassIssuance = (bool) ($options['defer_pass_issuance'] ?? false);
        $withPass = $deferPassIssuance
            ? false
            : ($scope->requiresRegistration()
                ? true
                : ($options['with_pass'] ?? $scope->shouldIssuePasses));

        $status = ($withPass || $deferPassIssuance) ? Confirmed::name() : Interested::name();
        $source = $deferPassIssuance
            ? 'order'
            : ($withPass ? 'free_rsvp' : 'free_optional_rsvp');
        $idempotencyKey = $this->idempotencyKey($options);

        $scopeData = $scope->toRegistrationData();
        $registrations = DB::transaction(function () use (
            $idempotencyKey,
            $participants,
            $registrant,
            $scope,
            $scopeData,
            $source,
            $status,
        ): Collection {
            $this->lockScope->handle($scope);

            $existing = $this->findIdempotentRegistrations($scope, $idempotencyKey, count($participants));

            if ($existing !== null) {
                return $existing;
            }

            $this->checkCapacity($scope, count($participants));

            $registrations = new Collection;

            foreach ($participants as $participant) {
                $registrations->push($this->registrations->register(array_merge($scopeData, [
                    'registrant_type' => $registrant?->getMorphClass(),
                    'registrant_id' => $registrant?->getKey(),
                    'registration_type' => 'individual',
                    'status' => $status,
                    'source' => $source,
                    'total_participants' => 1,
                    'total_amount' => null,
                    'currency' => null,
                    'payment_status' => null,
                    'metadata' => $idempotencyKey === null ? null : [
                        'registration' => [
                            'idempotency_key' => $idempotencyKey,
                        ],
                    ],
                    'participants' => [$participant],
                ])));
            }

            return $registrations;
        });

        foreach ($registrations as $registration) {
            $this->dispatchConfirmedEvent($registration, $withPass);
        }

        return $registrations;
    }

    /**
     * @return Collection<int, EventRegistration>|null
     */
    private function findIdempotentRegistrations(
        EventRegistrationScope $scope,
        ?string $idempotencyKey,
        int $expectedCount,
    ): ?Collection {
        if ($idempotencyKey === null) {
            return null;
        }

        $registrationClass = ModelResolver::registrationClass();
        $query = $registrationClass::query()
            ->where('event_id', $scope->event->getKey());

        if ($scope->occurrence !== null) {
            $query->where('event_occurrence_id', $scope->occurrence->getKey());
        } else {
            $query->whereNull('event_occurrence_id');
        }

        if ($scope->session !== null) {
            $query->where('event_session_id', $scope->session->getKey());
        } else {
            $query->whereNull('event_session_id');
        }

        $existing = $query->get()->filter(
            static fn (EventRegistration $registration): bool => data_get(
                $registration->metadata ?? [],
                'registration.idempotency_key',
            ) === $idempotencyKey,
        )->values();

        if ($existing->isEmpty()) {
            return null;
        }

        if ($existing->count() !== $expectedCount) {
            throw new RuntimeException('The idempotency key is already associated with an incomplete registration batch.');
        }

        return $existing;
    }

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

    private function checkCapacity(EventRegistrationScope $scope, int $participantCount): void
    {
        $remaining = $scope->capacityRemaining();

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

    private function throwOpenDoorException(EventRegistrationScope $scope): never
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

    private function dispatchConfirmedEvent(EventRegistration $registration, bool $withPass): void
    {
        EventFreeRegistrationConfirmed::dispatch($registration, $withPass);
    }
}
