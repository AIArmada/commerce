<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSeriesResource\Pages;

use AIArmada\FilamentEvents\Resources\EventSeriesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditEventSeries extends EditRecord
{
    protected static string $resource = EventSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
