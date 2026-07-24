<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\TitleResource\Pages;

use AIArmada\FilamentPersons\Resources\TitleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditTitle extends EditRecord
{
    protected static string $resource = TitleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
