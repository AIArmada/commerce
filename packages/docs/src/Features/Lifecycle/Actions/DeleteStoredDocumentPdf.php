<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Lifecycle\Actions;

use AIArmada\Docs\Contracts\DocumentPdfCleanup;
use AIArmada\Docs\Support\DocPdfPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final readonly class DeleteStoredDocumentPdf implements DocumentPdfCleanup
{
    public function execute(string $docId, string $docType, ?string $path): void
    {
        if ($path === null || $path !== DocPdfPath::forDocument($docType, $docId)) {
            return;
        }

        try {
            if (! Storage::disk(DocPdfPath::diskForType($docType))->delete($path)) {
                Log::warning('Failed to delete stored document PDF.', [
                    'doc_id' => $docId,
                    'pdf_path' => $path,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to delete stored document PDF.', [
                'doc_id' => $docId,
                'pdf_path' => $path,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
