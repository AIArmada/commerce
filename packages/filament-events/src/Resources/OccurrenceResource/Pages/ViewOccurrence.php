<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\OccurrenceResource\Pages;

use AIArmada\FilamentEvents\Resources\OccurrenceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewOccurrence extends ViewRecord
{
    protected static string $resource = OccurrenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
