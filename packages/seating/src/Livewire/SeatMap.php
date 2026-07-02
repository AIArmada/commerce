<?php

declare(strict_types=1);

namespace AIArmada\Seating\Livewire;

use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatMap as SeatMapModel;
use AIArmada\Seating\Services\SeatLayoutRenderer;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class SeatMap extends Component
{
    public ?string $seatMapId = null;

    public ?string $seatableType = null;

    public ?string $seatableId = null;

    public bool $selectable = true;

    public bool $showLegend = true;

    public ?string $category = null;

    /** @var array<int, string> */
    public array $picked = [];

    public function mount(
        ?string $seatMapId = null,
        ?string $seatableType = null,
        ?string $seatableId = null,
        bool $selectable = true,
        bool $showLegend = true,
        ?string $category = null,
    ): void {
        $this->seatMapId = $seatMapId;
        $this->seatableType = $seatableType;
        $this->seatableId = $seatableId;
        $this->selectable = $selectable;
        $this->showLegend = $showLegend;
        $this->category = $category;
    }

    public function toggleSeat(string $seatId): void
    {
        if (! $this->selectable) {
            return;
        }

        $map = $this->resolveMap();

        if ($map === null) {
            return;
        }

        $seat = Seat::query()
            ->whereKey($seatId)
            ->whereHas('section', fn (Builder $query): Builder => $query->where('seat_map_id', $map->id))
            ->first();

        if ($seat === null || $seat->status !== 'available') {
            return;
        }

        $now = now();

        $hasActiveHold = $seat->holds()
            ->where('expires_at', '>', $now)
            ->exists();

        $hasActiveAllocation = $seat->allocations()
            ->where('state', 'active')
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

        return app(SeatLayoutRenderer::class)->describe($map);
    }

    public function getStatusProperty(): array
    {
        $map = $this->resolveMap();
        if ($map === null) {
            return [];
        }

        $seats = $map->sections()
            ->with(['seats.holds', 'seats.allocations'])
            ->get()
            ->flatMap(fn ($section) => $section->seats);

        $status = [];
        $now = now();

        foreach ($seats as $seat) {
            if ($seat->status === 'blocked') {
                $status[$seat->id] = 'blocked';

                continue;
            }

            $activeHold = $seat->holds->first(fn ($hold) => $hold->expires_at?->greaterThan($now));
            if ($activeHold !== null) {
                $status[$seat->id] = 'held';

                continue;
            }

            $activeAlloc = $seat->allocations->first(fn ($alloc) => $alloc->state === 'active');
            if ($activeAlloc !== null) {
                $status[$seat->id] = 'sold';

                continue;
            }

            $status[$seat->id] = in_array($seat->id, $this->picked, true) ? 'picked' : 'available';
        }

        return $status;
    }

    public function render(): mixed
    {
        return view('seating::livewire.seat-map');
    }

    private function resolveMap(): ?SeatMapModel
    {
        if ($this->seatMapId !== null) {
            return SeatMapModel::query()->with('sections.seats')->find($this->seatMapId);
        }

        if ($this->seatableType !== null && $this->seatableId !== null) {
            return SeatMapModel::query()
                ->where('seatable_type', $this->seatableType)
                ->where('seatable_id', $this->seatableId)
                ->active()
                ->with('sections.seats')
                ->first();
        }

        return null;
    }
}
