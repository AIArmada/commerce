<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocEmailTemplateResource\Pages;

use AIArmada\Docs\Contracts\DocumentEmailTemplates;
use AIArmada\Docs\Features\Delivery\Models\DocEmailTemplate;
use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateDocEmailTemplate extends CreateRecord
{
    protected static string $resource = DocEmailTemplateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $id = app(DocumentEmailTemplates::class)->create($data);

        return DocEmailTemplate::query()->findOrFail($id);
    }
}
