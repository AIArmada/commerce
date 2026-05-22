<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSeriesResource\Pages;

use AIArmada\FilamentEvents\Resources\EventSeriesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListEventSeries extends ListRecords
{
    protected static string $resource = EventSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
