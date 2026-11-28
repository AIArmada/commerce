<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocSequenceResource\Pages;

use AIArmada\Docs\Contracts\DocumentSequenceSettings;
use AIArmada\Docs\Features\Numbering\Models\DocSequence;
use AIArmada\FilamentDocs\Resources\DocSequenceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditDocSequence extends EditRecord
{
    protected static string $resource = DocSequenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->using(function (DocSequence $record): bool {
                    app(DocumentSequenceSettings::class)->remove([(string) $record->getKey()]);

                    return true;
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof DocSequence, 404);
        app(DocumentSequenceSettings::class)->update((string) $record->getKey(), $data);

        return $record->refresh();
    }
}
