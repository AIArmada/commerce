<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\TitleIssuerResource\Pages;

use AIArmada\FilamentPersons\Resources\TitleIssuerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditTitleIssuer extends EditRecord
{
    protected static string $resource = TitleIssuerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
