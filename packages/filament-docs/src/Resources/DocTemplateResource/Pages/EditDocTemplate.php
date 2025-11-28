<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages;

use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

final class EditDocTemplate extends EditRecord
{
    protected static string $resource = DocTemplateResource::class;

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
