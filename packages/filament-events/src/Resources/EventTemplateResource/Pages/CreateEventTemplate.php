<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTemplateResource\Pages;

use AIArmada\Events\Features\Catalog\Actions\ManageEventTemplateAction;
use AIArmada\FilamentEvents\Resources\EventTemplateResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateEventTemplate extends CreateRecord
{
    protected static string $resource = EventTemplateResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(ManageEventTemplateAction::class)->save(null, $data, Filament::auth()->user());
    }
}
