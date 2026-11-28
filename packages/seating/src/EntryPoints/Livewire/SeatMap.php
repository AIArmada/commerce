<?php

declare(strict_types=1);

namespace AIArmada\Seating\EntryPoints\Livewire;

use AIArmada\Seating\Contracts\SeatLayoutInterface;
use AIArmada\Seating\Data\SeatMapRef;
use AIArmada\Seating\Enums\SeatStatus;
use AIArmada\Seating\Features\Maps\Api\MapsApi;
use AIArmada\Seating\Features\Reservations\Api\ReservationsApi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SeatMap extends Component
{
    public const int MAX_SELECTION = 100;

    public const int TOGGLE_RATE_LIMIT = 60;

    #[Locked]
    public ?string $seatMapId = null;

    #[Locked]
    public ?string $seatableType = null;

    #[Locked]
    public ?string $seatableId = null;

    #[Locked]
    public ?string $sectionId = null;

    #[Locked]
    public bool $selectable = true;

    public bool $showLegend = true;

    public ?string $category = null;

    /** @var array<int, string> */
    #[Locked]
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

        $index = array_search($seatId, $this->picked, true);

        if ($index !== false) {
            unset($this->picked[$index]);
            $this->picked = array_values($this->picked);
            $this->dispatch('seat-deselected', seatId: $seatId);

            return;
        }

        if (count($this->picked) >= self::MAX_SELECTION) {
            return;
        }

        $seat = app(MapsApi::class)->seatForSelection($seatId, $mapId, $this->sectionId);

        if ($seat === null || $seat->status !== SeatStatus::Available->value) {
            return;
        }

        if (app(ReservationsApi::class)->isSeatOccupied($seatId, CarbonImmutable::now())) {
            return;
        }

        $this->picked[] = $seatId;
        $this->dispatch('seat-picked', seatId: $seatId);
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

        return app(SeatLayoutInterface::class)->describe($map->id, $this->sectionId);
    }

    public function getStatusProperty(): array
    {
        $map = $this->resolveMap();

        if ($map === null) {
            return [];
        }

        $rawStatuses = app(MapsApi::class)->seatStatuses($map->id, $this->sectionId);
        $occupancy = app(ReservationsApi::class)->seatOccupancy(array_keys($rawStatuses), CarbonImmutable::now());
        $status = [];

        foreach ($rawStatuses as $seatId => $rawStatus) {
            if ($rawStatus === SeatStatus::Blocked->value) {
                $status[$seatId] = SeatStatus::Blocked->value;

                continue;
            }

            if (($occupancy[$seatId] ?? null) === 'held') {
                $status[$seatId] = SeatStatus::Held->value;

                continue;
            }

            if (($occupancy[$seatId] ?? null) === 'sold') {
                $status[$seatId] = SeatStatus::Sold->value;

                continue;
            }

            $status[$seatId] = in_array($seatId, $this->picked, true)
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
        return app(MapsApi::class)->resolveActiveMapId(
            $this->seatMapId,
            $this->seatableType,
            $this->seatableId,
        );
    }

    private function resolveMap(): ?SeatMapRef
    {
        return app(MapsApi::class)->resolveMap(
            $this->seatMapId,
            $this->seatableType,
            $this->seatableId,
            activeOnly: $this->seatMapId === null,
        );
    }
}
