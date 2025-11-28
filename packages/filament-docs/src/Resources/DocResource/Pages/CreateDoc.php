<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\Pages;

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use AIArmada\FilamentDocs\Resources\DocResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateDoc extends CreateRecord
{
    protected static string $resource = DocResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $docService = app(DocService::class);

        if (empty($data['doc_number'])) {
            $data['doc_number'] = $docService->generateDocNumber($data['doc_type'] ?? 'invoice');
        }

        if (empty($data['issue_date'])) {
            $data['issue_date'] = now();
        }

        if (empty($data['company_data'])) {
            $data['company_data'] = config('docs.company');
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if (config('filament-docs.auto_generate_pdf', true)) {
            $record = $this->record;

            if ($record instanceof Doc) {
                $docService = app(DocService::class);
                $docService->generatePdf($record, save: true);
            }
        }
    }
}
