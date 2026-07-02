<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Resources\SeatMapResource\Pages;

use AIArmada\FilamentSeating\Resources\SeatMapResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateSeatMap extends CreateRecord
{
    protected static string $resource = SeatMapResource::class;
}
