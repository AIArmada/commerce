<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Maps\Queries;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Seating\Data\SeatMapRef;
use AIArmada\Seating\Data\SeatSelectionData;
use AIArmada\Seating\Features\Maps\Api\MapsApi;
use AIArmada\Seating\Features\Maps\Models\Seat;
use AIArmada\Seating\Features\Maps\Models\SeatMap;
use AIArmada\Seating\Features\Maps\Persistence\SeatMapLayoutCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final readonly class EloquentMapsApi implements MapsApi
{
    public function __construct(private SeatMapLayoutCache $cache) {}

    public function resolveActiveMapId(?string $seatMapId, ?string $seatableType, ?string $seatableId): ?string
    {
        return $this->resolveMap($seatMapId, $seatableType, $seatableId, activeOnly: true)?->id;
    }

    public function resolveMap(?string $seatMapId, ?string $seatableType, ?string $seatableId, bool $activeOnly = true): ?SeatMapRef
    {
        $query = SeatMap::query();

        if ($activeOnly) {
            $query->active();
        }

        if ($seatMapId !== null && $seatMapId !== '') {
            $query->whereKey($seatMapId);
        } else {
            $type = $this->normaliseMorphType($seatableType);

            if ($type === null || $seatableId === null || $seatableId === '') {
                return null;
            }

            $query->where('seatable_type', $type)->where('seatable_id', $seatableId);
        }

        $map = $query->orderBy('created_at')->orderBy('id')->first();

        return $map instanceof SeatMap
            ? new SeatMapRef(
                id: (string) $map->getKey(),
                name: $map->name,
                slug: $map->slug,
                version: $map->version,
            )
            : null;
    }

    public function seatForSelection(string $seatId, string $mapId, ?string $sectionId = null): ?SeatSelectionData
    {
        $seat = Seat::query()
            ->whereKey($seatId)
            ->whereHas('section', fn (Builder $query): Builder => $query->where('seat_map_id', $mapId))
            ->when($sectionId !== null && $sectionId !== '', fn (Builder $query): Builder => $query->where('seat_section_id', $sectionId))
            ->first();

        if (! $seat instanceof Seat) {
            return null;
        }

        return new SeatSelectionData(
            id: (string) $seat->getKey(),
            sectionId: (string) $seat->seat_section_id,
            status: $seat->status->value,
        );
    }

    public function seatStatuses(string $mapId, ?string $sectionId = null): array
    {
        return Seat::query()
            ->whereHas('section', fn (Builder $query): Builder => $query->where('seat_map_id', $mapId))
            ->when($sectionId !== null && $sectionId !== '', fn (Builder $query): Builder => $query->where('seat_section_id', $sectionId))
            ->orderBy('id')
            ->get(['id', 'status'])
            ->mapWithKeys(static fn (Seat $seat): array => [(string) $seat->getKey() => $seat->status->value])
            ->all();
    }

    public function layout(string $mapId, ?string $sectionId = null): array
    {
        $scope = SeatMap::ownerScopeConfig();
        $owner = OwnerContext::resolveConfigured($scope);
        OwnerContext::assertResolvedOrExplicitGlobal($owner);

        $includeGlobal = ! OwnerScopeOverride::suppressIncludeGlobal() && $scope->includeGlobal;
        $map = SeatMap::query()->findOrFail($mapId);
        $logicalKey = sprintf(
            'seating.layout.%s.%d.%s.%s',
            (string) $map->getKey(),
            $map->version,
            $sectionId ?? 'all',
            $includeGlobal ? 'global' : 'private',
        );

        return $this->cache->remember($owner, $logicalKey, fn (): array => $this->buildLayout($map, $sectionId));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLayout(SeatMap $map, ?string $sectionId): array
    {
        $loadedSections = $map->sections()
            ->when($sectionId !== null && $sectionId !== '', fn (Builder $query): Builder => $query->whereKey($sectionId))
            ->orderBy('sort_order')
            ->with(['seats' => fn ($query) => $query->orderBy('row_number')->orderBy('column_number')])
            ->get();

        $sections = [];
        $seats = [];
        $maxRows = 0;
        $maxCols = 0;

        foreach ($loadedSections as $section) {
            $sectionSeats = [];

            foreach ($section->seats as $seat) {
                $maxRows = max($maxRows, $seat->row_number);
                $maxCols = max($maxCols, $seat->column_number);
                $sectionSeats[] = [
                    'id' => $seat->id,
                    'section' => $section->code,
                    'row' => $seat->row_label,
                    'label' => $seat->seat_label,
                    'column' => $seat->column_number,
                    'category' => $seat->category,
                ];
            }

            array_push($seats, ...$sectionSeats);
            $sections[] = [
                'id' => $section->id,
                'code' => $section->code,
                'name' => $section->name,
                'sort_order' => $section->sort_order,
                'seats' => $sectionSeats,
            ];
        }

        return [
            'map' => [
                'id' => $map->id,
                'name' => $map->name,
                'slug' => $map->slug,
            ],
            'sections' => $sections,
            'seats' => $seats,
            'bounds' => [
                'rows' => $maxRows,
                'cols' => $maxCols,
            ],
        ];
    }

    private function normaliseMorphType(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            return null;
        }

        /** @var Model $model */
        $model = new $class;

        return $model->getMorphClass();
    }
}
