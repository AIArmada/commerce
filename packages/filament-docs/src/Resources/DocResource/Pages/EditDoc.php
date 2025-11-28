<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\Pages;

use AIArmada\FilamentDocs\Resources\DocResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

final class EditDoc extends EditRecord
{
    protected static string $resource = DocResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->icon(Heroicon::OutlinedEye),
            Actions\DeleteAction::make()
                ->icon(Heroicon::OutlinedTrash),
        ];
    }
}
