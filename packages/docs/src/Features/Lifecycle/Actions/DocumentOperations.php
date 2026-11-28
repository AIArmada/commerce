<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\Data\DocData;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Runtime\DocumentRecords;

final readonly class DocumentOperations implements DocServiceInterface
{
    public function __construct(private DocumentRecords $records) {}

    public function create(DocData $data): string
    {
        return (string) $this->records->create($data)->getKey();
    }

    public function findForSubject(string $subjectType, string $subjectId, string $docType): ?string
    {
        $id = Doc::query()
            ->forOwner(includeGlobal: false)
            ->where('docable_type', $subjectType)
            ->where('docable_id', $subjectId)
            ->where('doc_type', $docType)
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id');

        return $id === null ? null : (string) $id;
    }

    public function update(string $docId, array $data, string $summary = 'Document updated'): void
    {
        $this->records->update($this->resolveDocument($docId), $data, $summary);
    }

    public function generatePdf(string $docId, bool $save = true): string
    {
        return $this->records->generatePdf($this->resolveDocument($docId), $save);
    }

    public function resolveStorageDiskForDocType(string $docType): string
    {
        return $this->records->resolveStorageDiskForDocType($docType);
    }

    private function resolveDocument(string $docId): Doc
    {
        return config('docs.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(Doc::class, $docId, includeGlobal: (bool) config('docs.owner.include_global', false))
            : Doc::query()->findOrFail($docId);
    }
}
