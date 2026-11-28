<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTemplateResource\Pages;

use AIArmada\Events\Features\Catalog\Actions\ManageEventTemplateAction;
use AIArmada\FilamentEvents\Resources\EventTemplateResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditEventTemplate extends EditRecord
{
    protected static string $resource = EventTemplateResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ManageEventTemplateAction::class)->save((string) $record->getKey(), $data, Filament::auth()->user());
    }
}
