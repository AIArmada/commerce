<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\Pages;

use AIArmada\Docs\Data\DocData;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\FilamentDocs\Resources\DocResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateDoc extends CreateRecord
{
    protected static string $resource = DocResource::class;

    protected function handleRecordCreation(array $data): Doc
    {
        $docService = app(DocServiceInterface::class);

        $data['generate_pdf'] = $data['generate_pdf'] ?? (bool) config('filament-docs.features.auto_generate_pdf', false);

        $id = $docService->create(DocData::from($data));

        return Doc::query()->forOwner()->findOrFail($id);
    }
}
