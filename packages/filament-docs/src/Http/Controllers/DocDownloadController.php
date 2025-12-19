<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Http\Controllers;

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DocDownloadController
{
    public function __invoke(Doc $doc): BinaryFileResponse | StreamedResponse
    {
        DocsOwnerScope::assertCanAccessDoc($doc);

        if ($doc->pdf_path === null) {
            throw new NotFoundHttpException('PDF not found for this document.');
        }

        $disk = app(DocService::class)->resolveStorageDiskForDocType($doc->doc_type);
        $storage = Storage::disk($disk);

        if (! $storage->exists($doc->pdf_path)) {
            throw new NotFoundHttpException('PDF file not found.');
        }

        $filename = $this->generateFilename($doc);

        return $storage->download($doc->pdf_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function generateFilename(Doc $doc): string
    {
        $type = ucfirst($doc->doc_type);
        $number = str_replace(['/', '\\', ' '], '-', $doc->doc_number);

        return "{$type}-{$number}.pdf";
    }
}
