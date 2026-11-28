<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocumentPdfPublisher;
use AIArmada\Docs\Contracts\PdfRenderer;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocRenderService;
use AIArmada\Docs\Support\DocPdfPath;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final readonly class StoreDocumentPdf implements DocumentPdfPublisher
{
    public function __construct(
        private DocRenderService $renderer,
        private PdfRenderer $pdfRenderer,
    ) {}

    public function execute(string $id): string
    {
        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Doc::class, $id);
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $snapshot = Doc::query()->forOwner()->findOrFail($id);
            [$html, $options, $fingerprint] = $this->renderer->pdfInput($snapshot);
            $path = DocPdfPath::forDocument($snapshot->doc_type, $id);
            $disk = DocPdfPath::diskForType($snapshot->doc_type);
            $storage = Storage::disk($disk);

            if ($snapshot->pdf_path === $path && $snapshot->pdf_fingerprint === $fingerprint && $storage->exists($path)) {
                return $path;
            }

            // Keep the expensive PDF driver outside the database transaction.
            $bytes = $this->pdfRenderer->render($html, $options);

            $published = $snapshot->getConnection()->transaction(function () use ($id, $fingerprint, $path, $disk, $bytes): bool {
                $locked = Doc::query()->forOwner()->lockForUpdate()->findOrFail($id);
                [, , $currentFingerprint] = $this->renderer->pdfInput($locked);

                if ($currentFingerprint !== $fingerprint
                    || DocPdfPath::forDocument($locked->doc_type, $id) !== $path
                    || DocPdfPath::diskForType($locked->doc_type) !== $disk) {
                    return false;
                }

                $storage = Storage::disk($disk);

                if ($locked->pdf_path === $path && $locked->pdf_fingerprint === $fingerprint && $storage->exists($path)) {
                    return true;
                }

                if (! $storage->put($path, $bytes)) {
                    throw new RuntimeException('Document PDF could not be stored.');
                }

                $locked->forceFill(['pdf_path' => $path, 'pdf_fingerprint' => $fingerprint])->save();

                return true;
            });

            if ($published) {
                return $path;
            }
        }

        throw new RuntimeException('Document changed during PDF generation. Retry the request.');
    }
}
