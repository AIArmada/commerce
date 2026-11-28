<?php

declare(strict_types=1);

namespace AIArmada\Docs\Runtime;

use AIArmada\Docs\Data\DocData;
use AIArmada\Docs\Data\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\DocStatus;
use Illuminate\Database\Eloquent\Model;

interface DocumentRecords
{
    public function generateNumber(string $docType = 'invoice'): string;

    public function resolveStorageDiskForDocType(string $docType): string;

    public function create(DocData $data): Doc;

    /** @param array<string, mixed> $data */
    public function createFromType(DocType $type, array $data, ?Model $owner = null): Doc;

    /** @param array<string, mixed> $data */
    public function update(Doc $doc, array $data, string $summary = 'Document updated'): Doc;

    public function convert(Doc $source, DocType $targetType, ?Model $owner = null): Doc;

    public function clone(Doc $source, ?Model $owner = null): Doc;

    public function generatePdf(Doc $doc, bool $save = true): string;

    public function downloadPdf(Doc $doc): string;

    public function markAsSent(Doc $doc, ?string $notes = null): void;

    public function updateStatus(Doc $doc, DocStatus | string $status, ?string $notes = null): void;

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{subtotal_minor: int, tax_amount_minor: int, total_minor: int}
     */
    public function calculateTotals(array $items, int $discountAmountMinor = 0, ?string $currency = null): array;
}
