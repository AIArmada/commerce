<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

use AIArmada\Docs\Data\DocData;

interface DocServiceInterface
{
    public function create(DocData $data): string;

    public function findForSubject(string $subjectType, string $subjectId, string $docType): ?string;

    /** @param array<string, mixed> $data */
    public function update(string $docId, array $data, string $summary = 'Document updated'): void;

    public function generatePdf(string $docId, bool $save = true): string;

    public function resolveStorageDiskForDocType(string $docType): string;
}
