<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocSequenceResource\Pages;

use AIArmada\Docs\Contracts\DocumentSequenceSettings;
use AIArmada\Docs\Features\Numbering\Models\DocSequence;
use AIArmada\FilamentDocs\Resources\DocSequenceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateDocSequence extends CreateRecord
{
    protected static string $resource = DocSequenceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $id = app(DocumentSequenceSettings::class)->create($data);

        return DocSequence::query()->findOrFail($id);
    }
}
