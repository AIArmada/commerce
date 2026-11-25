<?php

declare(strict_types=1);

namespace AIArmada\Events\Drivers;

use AIArmada\Events\Contracts\EventSeatingDriver;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\Seating\Actions\ConvertHoldsToAllocationsAction;
use AIArmada\Seating\Actions\EnsureSeatHoldAction;
use AIArmada\Seating\Actions\EnsureSectionAllocationAction;
use AIArmada\Seating\Actions\ReleaseAllocationsAction;
use AIArmada\Seating\Actions\ResolveSeatMapForHostAction;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Exceptions\SeatMapNotFoundException;
use AIArmada\Seating\Exceptions\SeatSectionNotFoundException;
use AIArmada\Seating\Models\SeatAllocation;
use AIArmada\Seating\Models\SeatHold;
use AIArmada\Seating\Models\SeatMap;
use AIArmada\Seating\Models\SeatSection;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Models\TicketTypeSeatingOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SeatingDriver implements EventSeatingDriver
{
    public function __construct(
        private readonly EnsureSeatHoldAction $ensureSeatHold,
        private readonly EnsureSectionAllocationAction $ensureSectionAllocation,
        private readonly ConvertHoldsToAllocationsAction $convertHolds,
        private readonly ResolveSeatMapForHostAction $resolveSeatMap,
        private readonly ReleaseAllocationsAction $releaseAllocations,
    ) {}

    public function hasActiveSeatMap(string $scopeType, string $scopeId): bool
    {
        $host = $this->scopeModel($scopeType, $scopeId);

        if (SeatMap::forHost($host)->active()->exists()) {
            return true;
        }

        if ($scopeType === 'session') {
            $occurrence = $this->related($host, 'occurrence');

            if ($occurrence instanceof Model && SeatMap::forHost($occurrence)->active()->exists()) {
                return true;
            }
        }

        if (in_array($scopeType, ['session', 'occurrence'], true)) {
            $event = $this->related($host, 'event');

            if ($event instanceof Model && SeatMap::forHost($event)->active()->exists()) {
                return true;
            }
        }

        return false;
    }

    public function holdSeats(
        string $scopeType,
        string $scopeId,
        string $seatingMode,
        int $quantity,
        ?string $heldByType = null,
        ?string $heldById = null,
    ): array {
        $mode = SeatingMode::from($seatingMode);

        if (! $mode->requiresAllocation() || $mode === SeatingMode::GeneralAdmission) {
            return [];
        }

        $map = $this->resolveSeatMap->handle($this->scopeModel($scopeType, $scopeId), $mode);

        if ($map === null) {
            return [];
        }

        $holds = $this->ensureSeatHold->handle(
            map: $map,
            quantity: $quantity,
            mode: $mode,
            heldByType: $heldByType,
            heldById: $heldById,
        );

        return $holds
            ->map(static fn (SeatHold $hold): string => (string) $hold->getKey())
            ->values()
            ->all();
    }

    public function allocateSeats(
        string $scopeType,
        string $scopeId,
        string $seatingMode,
        string $ticketTypeId,
        string $allocatedToType,
        string $allocatedToId,
        ?string $reference = null,
    ): array {
        $mode = SeatingMode::from($seatingMode);

        if (! $mode->requiresAllocation()) {
            return [];
        }

        $map = $this->resolveSeatMap->handle($this->scopeModel($scopeType, $scopeId), $mode);

        if ($map === null) {
            throw new SeatMapNotFoundException('No active seat map found for the event scope.');
        }

        if ($mode === SeatingMode::GeneralAdmission) {
            $section = $this->sectionFor(TicketType::query()->findOrFail($ticketTypeId));

            $allocation = $this->ensureSectionAllocation->handle(
                section: $section,
                allocToType: $allocatedToType,
                allocToId: $allocatedToId,
                reference: $reference,
            );

            return [(string) $allocation->getKey()];
        }

        $holds = $this->ensureSeatHold->handle(
            map: $map,
            quantity: 1,
            mode: $mode,
            heldByType: $allocatedToType,
            heldById: $allocatedToId,
            reference: $reference,
        );

        $allocations = $this->convertHolds->handle(
            holds: $holds,
            mode: $mode,
            allocToType: $allocatedToType,
            allocToId: $allocatedToId,
            reference: $reference,
        );

        return $allocations
            ->map(static fn (SeatAllocation $allocation): string => (string) $allocation->getKey())
            ->values()
            ->all();
    }

    public function releaseAllocations(string $allocatedToType, string $allocatedToId): int
    {
        return $this->releaseAllocations->handle($allocatedToType, $allocatedToId);
    }

    public function allocatePassSeats(string $passId): void
    {
        $pass = Pass::query()->with(['ticketType.ticketable'])->find($passId);

        if (! $pass instanceof Pass) {
            return;
        }

        $ticketType = $pass->ticketType;

        if (! $ticketType instanceof TicketType) {
            return;
        }

        $seatingMode = $ticketType->seating_mode;

        if ($seatingMode === null || ! $seatingMode->requiresAllocation()) {
            return;
        }

        $scope = $this->passScope($pass);

        if ($scope === null) {
            return;
        }

        $map = $this->resolveActiveMap($scope['type'], $scope['id']);

        if ($map === null) {
            throw new SeatMapNotFoundException('No active seat map found for the event scope.');
        }

        DB::transaction(function () use ($pass, $ticketType, $seatingMode, $map): void {
            if ($seatingMode === SeatingMode::GeneralAdmission) {
                $this->ensureSectionAllocation->handle(
                    section: $this->sectionFor($ticketType),
                    allocToType: $pass->getMorphClass(),
                    allocToId: (string) $pass->getKey(),
                    reference: $pass->pass_no,
                );

                return;
            }

            $holds = $this->ensureSeatHold->handle(
                map: $map,
                quantity: 1,
                mode: $seatingMode,
                heldByType: $pass->getMorphClass(),
                heldById: (string) $pass->getKey(),
                reference: (string) $pass->getKey(),
            );

            $this->convertHolds->handle(
                holds: $holds,
                mode: $seatingMode,
                allocToType: $pass->getMorphClass(),
                allocToId: (string) $pass->getKey(),
                reference: $pass->pass_no,
            );
        });
    }

    public function resolveScopeSeatMapId(string $scopeType, string $scopeId): ?string
    {
        $map = $this->resolveActiveMap($scopeType, $scopeId);

        return $map instanceof SeatMap ? (string) $map->getKey() : null;
    }

    public function deleteScopeChildren(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        if ($scopeType === 'registration') {
            $registrationClass = ModelResolver::registrationClass();
            $type = $morphClass ?? (new $registrationClass)->getMorphClass();

            $this->releaseAllocations->handle($type, $scopeId);

            return;
        }

        $modelClass = $this->scopeModelClass($scopeType);
        $type = $morphClass ?? (new $modelClass)->getMorphClass();

        SeatMap::query()
            ->where('seatable_type', $type)
            ->where('seatable_id', $scopeId)
            ->chunkById(200, function (iterable $maps): void {
                foreach ($maps as $map) {
                    $map->delete();
                }
            });
    }

    /**
     * @return array{type: string, id: string}|null
     */
    private function passScope(Pass $pass): ?array
    {
        $sessionClass = ModelResolver::sessionClass();
        $occurrenceClass = ModelResolver::occurrenceClass();
        $ticketable = $pass->ticketable;

        $sessionId = $pass->session_id ?? ($ticketable instanceof $sessionClass ? $ticketable->getKey() : null);

        if ($sessionId !== null) {
            return ['type' => 'session', 'id' => (string) $sessionId];
        }

        $occurrenceId = $pass->occurrence_id ?? ($ticketable instanceof $occurrenceClass ? $ticketable->getKey() : null);

        if ($occurrenceId !== null) {
            return ['type' => 'occurrence', 'id' => (string) $occurrenceId];
        }

        if ($ticketable instanceof Model) {
            return ['type' => 'event', 'id' => (string) $ticketable->getKey()];
        }

        return null;
    }

    private function resolveActiveMap(string $scopeType, string $scopeId): ?SeatMap
    {
        $host = $this->scopeModel($scopeType, $scopeId);
        $candidates = [$host];

        if ($scopeType === 'session') {
            $occurrence = $this->related($host, 'occurrence');

            if ($occurrence instanceof Model) {
                $candidates[] = $occurrence;
            }
        }

        if (in_array($scopeType, ['session', 'occurrence'], true)) {
            $event = $this->related($host, 'event');

            if ($event instanceof Model) {
                $candidates[] = $event;
            }
        }

        foreach ($candidates as $candidate) {
            $map = SeatMap::forHost($candidate)->active()->first();

            if ($map instanceof SeatMap) {
                return $map;
            }
        }

        return null;
    }

    private function sectionFor(TicketType $ticketType): SeatSection
    {
        $ticketType->loadMissing('seatingOptions.section');

        $option = $ticketType->seatingOptions->first();

        if (! $option instanceof TicketTypeSeatingOption) {
            throw new SeatSectionNotFoundException('No seating option configured for this ticket type.');
        }

        $section = $option->section;

        if (! $section instanceof SeatSection) {
            throw new SeatSectionNotFoundException('No seat section found for the configured seating option.');
        }

        return $section;
    }

    private function scopeModel(string $scopeType, string $scopeId): Model
    {
        $modelClass = $this->scopeModelClass($scopeType);

        $model = $modelClass::query()->findOrFail($scopeId);

        return $model;
    }

    /**
     * @return class-string<Model>
     */
    private function scopeModelClass(string $scopeType): string
    {
        return match ($scopeType) {
            'event' => ModelResolver::eventClass(),
            'occurrence' => ModelResolver::occurrenceClass(),
            'session' => ModelResolver::sessionClass(),
            default => throw new InvalidArgumentException(sprintf('Unsupported event scope type [%s].', $scopeType)),
        };
    }

    private function related(Model $model, string $relation): ?Model
    {
        if (! method_exists($model, $relation)) {
            return null;
        }

        $related = $model->getRelationValue($relation);

        return $related instanceof Model ? $related : null;
    }
}
