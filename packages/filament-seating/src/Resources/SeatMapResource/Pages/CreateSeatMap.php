<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Resources\SeatMapResource\Pages;

use AIArmada\FilamentSeating\Resources\SeatMapResource;
use AIArmada\Seating\Features\Maps\Actions\SaveSeatMap;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateSeatMap extends CreateRecord
{
    protected static string $resource = SeatMapResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        abort_unless(SeatMapResource::canCreate(), 403);

        return app(SaveSeatMap::class)->handle($data);
    }
}
