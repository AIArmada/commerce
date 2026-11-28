<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocEmailTemplateResource\Pages;

use AIArmada\Docs\Contracts\DocumentEmailTemplates;
use AIArmada\Docs\Features\Delivery\Models\DocEmailTemplate;
use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditDocEmailTemplate extends EditRecord
{
    protected static string $resource = DocEmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->using(function (DocEmailTemplate $record): bool {
                    app(DocumentEmailTemplates::class)->remove([(string) $record->getKey()]);

                    return true;
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof DocEmailTemplate, 404);
        app(DocumentEmailTemplates::class)->update((string) $record->getKey(), $data);

        return $record->refresh();
    }
}
