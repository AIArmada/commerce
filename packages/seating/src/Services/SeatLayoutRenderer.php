<?php

declare(strict_types=1);

namespace AIArmada\Seating\Services;

use AIArmada\Seating\Contracts\SeatLayoutInterface;
use AIArmada\Seating\Features\Maps\Api\MapsApi;

final readonly class SeatLayoutRenderer implements SeatLayoutInterface
{
    public function __construct(private MapsApi $maps) {}

    public function describe(string $mapId, ?string $sectionId = null): array
    {
        return $this->maps->layout($mapId, $sectionId);
    }
}
