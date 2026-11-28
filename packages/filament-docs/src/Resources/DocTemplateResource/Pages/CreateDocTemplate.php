<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages;

use AIArmada\Docs\Contracts\DocumentTemplates;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateDocTemplate extends CreateRecord
{
    protected static string $resource = DocTemplateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $id = app(DocumentTemplates::class)->create($data);

        return DocTemplate::query()->findOrFail($id);
    }
}
