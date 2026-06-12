<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

trait HasEventAddress
{
    public function eventLocationName(): string
    {
        return $this->location_name ?? $this->name ?? '';
    }

    public function eventAddress(): ?array
    {
        if ($this->address_line_1 === null && $this->city === null) {
            return null;
        }

        return [
            'line_1' => $this->address_line_1 ?? '',
            'line_2' => $this->address_line_2 ?? null,
            'city' => $this->city ?? '',
            'state' => $this->state ?? '',
            'postal_code' => $this->postal_code ?? '',
            'country' => $this->country ?? '',
        ];
    }

    public function eventCoordinates(): ?array
    {
        if ($this->latitude === null && $this->longitude === null) {
            return null;
        }

        return [
            'latitude' => (float) ($this->latitude ?? 0),
            'longitude' => (float) ($this->longitude ?? 0),
        ];
    }

    public function eventMapLinks(): ?array
    {
        if ($this->google_maps_url === null && $this->waze_url === null && $this->map_url === null) {
            return null;
        }

        return [
            'google_maps' => $this->google_maps_url ?? null,
            'waze' => $this->waze_url ?? null,
            'map' => $this->map_url ?? null,
        ];
    }

    public function eventDirections(): ?string
    {
        return $this->directions ?? null;
    }

    public function toEventLocationSnapshot(): array
    {
        return [
            'location_name' => $this->eventLocationName(),
            'address' => $this->eventAddress(),
            'coordinates' => $this->eventCoordinates(),
            'map_links' => $this->eventMapLinks(),
            'directions' => $this->eventDirections(),
        ];
    }
}
