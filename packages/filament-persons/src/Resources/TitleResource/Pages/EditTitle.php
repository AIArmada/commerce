<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\TitleResource\Pages;

use AIArmada\FilamentPersons\Resources\TitleResource;
use AIArmada\Persons\Actions\ReorderTitleAction;
use AIArmada\Persons\Models\Title;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditTitle extends EditRecord
{
    protected static string $resource = TitleResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Title $record */
        return app(ReorderTitleAction::class)->update($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
