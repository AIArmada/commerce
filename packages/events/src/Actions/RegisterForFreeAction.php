<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Enums\OpenDoorMode;
use AIArmada\Events\Enums\PricingMode;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class RegisterForFreeAction
{
    public function __construct(
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly RegistrationServiceInterface $registrations,
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
        EventWriteGuard::findOrFail($scope->event);

        if (! $scope->isFreeOnly() && $scope->pricingMode !== PricingMode::Mixed) {
            throw new NotFreeEventException(
                "Event {$scope->event->id} is not free. Use the paid path or set pricing_mode to 'free' or 'mixed'.",
            );
        }

        if ($scope->isOpenDoor()) {
            $this->throwOpenDoorException($scope);
        }

        $withPass = $scope->requiresRegistration()
            ? true
            : ($options['with_pass'] ?? $scope->shouldIssuePasses);

        $status = $withPass ? Confirmed::name() : Interested::name();
        $source = $withPass ? 'free_rsvp' : 'free_optional_rsvp';

        $scopeData = $scope->toRegistrationData();
        $registrations = DB::transaction(function () use (
            $participants,
            $registrant,
            $scope,
            $scopeData,
            $source,
            $status,
        ): Collection {
            $this->lockCapacityScope($scope);
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

    private function lockCapacityScope(EventRegistrationScope $scope): void
    {
        if ($scope->session !== null) {
            $scope->session->newQuery()
                ->whereKey($scope->session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return;
        }

        if ($scope->occurrence !== null) {
            $scope->occurrence->newQuery()
                ->whereKey($scope->occurrence->getKey())
                ->lockForUpdate()
                ->firstOrFail();
        }
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
