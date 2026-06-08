<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSubLocationResource\Pages;

use AIArmada\FilamentEvents\Resources\EventSubLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditEventSubLocation extends EditRecord
{
    protected static string $resource = EventSubLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
