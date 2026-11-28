<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\Pages;

use AIArmada\Docs\Contracts\DocumentLifecycle;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\FilamentDocs\Resources\DocResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

final class EditDoc extends EditRecord
{
    protected static string $resource = DocResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->icon(Heroicon::OutlinedEye),
            Actions\DeleteAction::make()
                ->icon(Heroicon::OutlinedTrash)
                ->using(function (Doc $record): bool {
                    app(DocumentLifecycle::class)->delete([(string) $record->getKey()]);

                    return true;
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Doc) {
            abort(404);
        }

        app(DocServiceInterface::class)->update((string) $record->getKey(), $data);

        return $record->refresh();
    }
}
