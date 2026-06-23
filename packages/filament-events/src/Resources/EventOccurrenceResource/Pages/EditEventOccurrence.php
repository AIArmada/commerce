<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventOccurrenceResource\Pages;

use AIArmada\FilamentEvents\Resources\EventOccurrenceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditEventOccurrence extends EditRecord
{
    protected static string $resource = EventOccurrenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
