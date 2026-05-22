<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSeriesResource\Pages;

use AIArmada\FilamentEvents\Resources\EventSeriesResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewEventSeries extends ViewRecord
{
    protected static string $resource = EventSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
