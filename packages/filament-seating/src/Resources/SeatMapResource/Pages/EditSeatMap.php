<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Resources\SeatMapResource\Pages;

use AIArmada\FilamentSeating\Resources\SeatMapResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use AIArmada\Seating\Features\Maps\Actions\SaveSeatMap;
use AIArmada\Seating\Features\Maps\Models\SeatMap;
use Illuminate\Database\Eloquent\Model;

final class EditSeatMap extends EditRecord
{
    protected static string $resource = SeatMapResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof SeatMap && SeatMapResource::canEdit($record), 403);

        return app(SaveSeatMap::class)->handle($data, $record);
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }
}
