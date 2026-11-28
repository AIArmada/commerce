<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

interface DocumentEInvoiceSubmissions
{
    /** @param array<string, mixed> $data */
    public function record(string $docId, string $submissionUid, array $data = []): string;

    /** @param array<string, mixed> $data */
    public function update(string $docId, string $submissionId, array $data): void;
}
