<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Tax\Api;

interface TaxDocumentApi
{
    public function generateForAffiliate(string $affiliateId, int $year): ?TaxDocumentRecord;

    /** @return list<TaxDocumentRecord> */
    public function generateAnnualDocuments(int $year): array;

    public function calculateAnnualPayouts(string $affiliateId, int $year, ?string $currency = null): int;

    /** @return list<array{reference: string, currency: string, amount_minor: int}> */
    public function excludedPayoutsForYear(string $affiliateId, int $year, ?string $currency = null): array;

    /** @return list<string> */
    public function affiliatesRequiring1099(int $year): array;

    /** @return list<TaxDocumentRecord> */
    public function documentsFor(string $affiliateId, int $limit = 100): array;

    public function markSent(string $documentId): TaxDocumentRecord;

    public function regenerate(string $documentId): TaxDocumentRecord;
}
