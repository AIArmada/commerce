<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages;

use AIArmada\Docs\Contracts\DocumentTemplates;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

final class EditDocTemplate extends EditRecord
{
    protected static string $resource = DocTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->icon(Heroicon::OutlinedEye),
            Actions\DeleteAction::make()
                ->icon(Heroicon::OutlinedTrash)
                ->using(function (DocTemplate $record): bool {
                    app(DocumentTemplates::class)->remove((string) $record->getKey());

                    return true;
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof DocTemplate, 404);
        app(DocumentTemplates::class)->update((string) $record->getKey(), $data);

        return $record->refresh();
    }
}
