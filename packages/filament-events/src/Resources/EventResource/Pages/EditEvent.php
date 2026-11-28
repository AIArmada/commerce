<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\Pages;

use AIArmada\Events\Features\Catalog\Actions\ManageEventAction;
use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ManageEventAction::class)->save((string) $record->getKey(), $data, Filament::auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->using(fn (Model $record): bool => app(ManageEventAction::class)->delete((string) $record->getKey(), Filament::auth()->user())),
        ];
    }
}
