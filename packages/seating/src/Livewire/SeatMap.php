<?php

declare(strict_types=1);

namespace AIArmada\Seating\Livewire;

use AIArmada\Seating\Enums\SeatStatus;
use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatMap as SeatMapModel;
use AIArmada\Seating\Services\SeatLayoutRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class SeatMap extends Component
{
    public const int MAX_SELECTION = 100;

    public const int TOGGLE_RATE_LIMIT = 60;

    public ?string $seatMapId = null;

    public ?string $seatableType = null;

    public ?string $seatableId = null;

    public ?string $sectionId = null;

    public bool $selectable = true;

    public bool $showLegend = true;

    public ?string $category = null;

    /** @var array<int, string> */
    public array $picked = [];

    public function mount(
        ?string $seatMapId = null,
        ?string $seatableType = null,
        ?string $seatableId = null,
        ?string $sectionId = null,
        bool $selectable = true,
        bool $showLegend = true,
        ?string $category = null,
    ): void {
        $this->seatMapId = $seatMapId;
        $this->seatableType = $seatableType;
        $this->seatableId = $seatableId;
        $this->sectionId = $sectionId;
        $this->selectable = $selectable;
        $this->showLegend = $showLegend;
        $this->category = $category;
    }

    public function toggleSeat(string $seatId): void
    {
        if (! $this->selectable) {
            return;
        }

        $mapId = $this->resolveMapId();

        if ($mapId === null) {
            return;
        }

        if (! $this->attemptToggleRateLimit($mapId)) {
            return;
        }

        $seat = Seat::query()
            ->whereKey($seatId)
            ->whereHas('section', fn (Builder $query): Builder => $query->where('seat_map_id', $mapId))
            ->first();

        if ($seat === null || $seat->status !== SeatStatus::Available) {
            return;
        }

        $now = CarbonImmutable::now();

        $hasActiveHold = $seat->holds()
            ->where('expires_at', '>', $now)
            ->exists();

        $hasActiveAllocation = $seat->allocations()
            ->where('status', 'active')
            ->exists();

        if ($hasActiveHold || $hasActiveAllocation) {
            return;
        }

        $index = array_search($seatId, $this->picked, true);

        if ($index !== false) {
            unset($this->picked[$index]);
            $this->picked = array_values($this->picked);
            $this->dispatch('seat-deselected', seatId: $seatId);
        } else {
            if (count($this->picked) >= self::MAX_SELECTION) {
                return;
            }

            $this->picked[] = $seatId;
            $this->dispatch('seat-picked', seatId: $seatId);
        }
    }

    public function clearSelection(): void
    {
        $this->picked = [];
        $this->dispatch('selection-cleared');
    }

    public function getLayoutProperty(): array
    {
        $map = $this->resolveMap();

        if ($map === null) {
            return ['map' => null, 'sections' => [], 'seats' => [], 'bounds' => ['rows' => 0, 'cols' => 0]];
        }

        $sectionKey = $this->sectionId ?? 'all';

        return Cache::remember(
            "seating:layout:{$map->getKey()}:v{$map->version}:section:{$sectionKey}",
            60,
            fn (): array => app(SeatLayoutRenderer::class)->describe($map, $this->sectionId),
        );
    }

    public function getStatusProperty(): array
    {
        $map = $this->resolveMap();
        if ($map === null) {
            return [];
        }

        $now = CarbonImmutable::now();

        $seats = $map->sections()
            ->when($this->sectionId !== null, fn (Builder $query): Builder => $query->whereKey($this->sectionId))
            ->with([
                'seats' => fn ($query): mixed => $query->select('id', 'seat_section_id', 'status'),
                'seats.holds' => fn ($query): mixed => $query
                    ->select('id', 'seat_id', 'expires_at')
                    ->where('expires_at', '>', $now),
                'seats.allocations' => fn ($query): mixed => $query
                    ->select('id', 'seat_id', 'status')
                    ->where('status', 'active'),
            ])
            ->get()
            ->flatMap(fn ($section) => $section->seats);

        $status = [];

        foreach ($seats as $seat) {
            if ($seat->status === SeatStatus::Blocked) {
                $status[$seat->id] = SeatStatus::Blocked->value;

                continue;
            }

            if ($seat->holds->isNotEmpty()) {
                $status[$seat->id] = SeatStatus::Held->value;

                continue;
            }

            if ($seat->allocations->isNotEmpty()) {
                $status[$seat->id] = SeatStatus::Sold->value;

                continue;
            }

            $status[$seat->id] = in_array($seat->id, $this->picked, true)
                ? SeatStatus::Picked->value
                : SeatStatus::Available->value;
        }

        return $status;
    }

    public function render(): mixed
    {
        return view('seating::livewire.seat-map');
    }

    private function attemptToggleRateLimit(string $mapId): bool
    {
        return (bool) RateLimiter::attempt(
            'seat-map-toggle:' . session()->getId() . ':' . $mapId,
            self::TOGGLE_RATE_LIMIT,
            static fn (): bool => true,
            60,
        );
    }

    private function resolveMapId(): ?string
    {
        if ($this->seatMapId !== null) {
            $id = SeatMapModel::query()->whereKey($this->seatMapId)->value('id');

            return is_string($id) ? $id : null;
        }

        $seatableClass = $this->resolveSeatableClass();

        if ($seatableClass === null || $this->seatableId === null) {
            return null;
        }

        $id = SeatMapModel::query()
            ->where('seatable_type', $this->seatableType)
            ->where('seatable_id', $this->seatableId)
            ->active()
            ->value('id');

        return is_string($id) ? $id : null;
    }

    private function resolveMap(): ?SeatMapModel
    {
        if ($this->seatMapId !== null) {
            return SeatMapModel::query()->find($this->seatMapId);
        }

        $seatableClass = $this->resolveSeatableClass();

        if ($seatableClass === null || $this->seatableId === null) {
            return null;
        }

        return SeatMapModel::query()
            ->where('seatable_type', $this->seatableType)
            ->where('seatable_id', $this->seatableId)
            ->active()
            ->first();
    }

    private function resolveSeatableClass(): ?string
    {
        if ($this->seatableType === null) {
            return null;
        }

        $class = Relation::getMorphedModel($this->seatableType) ?? $this->seatableType;

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class;
    }
}
