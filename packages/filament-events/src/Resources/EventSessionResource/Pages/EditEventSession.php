<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSessionResource\Pages;

use AIArmada\FilamentEvents\Resources\EventSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditEventSession extends EditRecord
{
    protected static string $resource = EventSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
