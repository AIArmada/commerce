<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\Pages;

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
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
                ->icon(Heroicon::OutlinedTrash),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Doc) {
            return parent::handleRecordUpdate($record, $data);
        }

        return app(DocService::class)->update($record, $data);
    }
}
