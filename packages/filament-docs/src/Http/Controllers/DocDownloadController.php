<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Http\Controllers;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DocDownloadController
{
    public function __invoke(Doc | string $doc): BinaryFileResponse | StreamedResponse
    {
        $ownerEnabled = (bool) config('docs.owner.enabled', false);

        if ($doc instanceof Doc) {
            $docModel = $doc;
        } else {
            $includeGlobal = (bool) config('docs.owner.include_global', false);

            if ($ownerEnabled) {
                try {
                    /** @var Doc $docModel */
                    $docModel = OwnerWriteGuard::findOrFailForOwner(Doc::class, $doc, OwnerContext::CURRENT, $includeGlobal);
                } catch (AuthorizationException) {
                    throw new NotFoundHttpException('Document not found.');
                }
            } else {
                $docModel = Doc::query()->find($doc);

                if (! $docModel instanceof Doc) {
                    throw new NotFoundHttpException('Document not found.');
                }
            }
        }

        if ($ownerEnabled) {
            DocsOwnerScope::assertCanAccessDoc($docModel);
        }

        if ($docModel->pdf_path === null) {
            throw new NotFoundHttpException('PDF not found for this document.');
        }

        $disk = app(DocService::class)->resolveStorageDiskForDocType($docModel->doc_type);
        $storage = Storage::disk($disk);

        if (! $storage->exists($docModel->pdf_path)) {
            throw new NotFoundHttpException('PDF file not found.');
        }

        $filename = $this->generateFilename($docModel);

        return $storage->download($docModel->pdf_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function generateFilename(Doc $doc): string
    {
        $type = ucfirst((string) $doc->doc_type);

        $type = preg_replace('/[^A-Za-z0-9_-]+/', '-', $type) ?? 'Document';
        $number = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $doc->doc_number) ?? '';

        $base = mb_trim($type . '-' . $number, '-');
        $base = mb_ltrim($base, '.');
        $base = mb_substr($base, 0, 150);

        if ($base === '') {
            $base = 'Document';
        }

        return $base . '.pdf';
    }
}
