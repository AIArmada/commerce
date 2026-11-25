<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Tax\Queries;

use AIArmada\Affiliates\Features\Tax\Actions\GenerateTaxDocument;
use AIArmada\Affiliates\Features\Tax\Actions\MarkTaxDocumentSent;
use AIArmada\Affiliates\Features\Tax\Api\TaxDocumentApi;
use AIArmada\Affiliates\Features\Tax\Api\TaxDocumentRecord;
use AIArmada\Affiliates\Features\Tax\Models\AffiliateTaxDocument;
use LogicException;

final readonly class TaxDocumentGateway implements TaxDocumentApi
{
    public function __construct(private TaxPayoutQuery $payouts, private TaxDocumentQuery $documents, private GenerateTaxDocument $generate, private MarkTaxDocumentSent $markSent) {}

    public function generateForAffiliate(string $affiliateId, int $year): ?TaxDocumentRecord
    {
        return $this->generate->execute($affiliateId, $year);
    }

    public function generateAnnualDocuments(int $year): array
    {
        $result = [];
        foreach ($this->payouts->affiliatesRequiring1099($year) as $id) {
            $document = $this->generate->execute($id, $year);
            if ($document !== null) {
                $result[] = $document;
            }
        }

        return $result;
    }

    public function calculateAnnualPayouts(string $affiliateId, int $year, ?string $currency = null): int
    {
        return $this->payouts->total($affiliateId, $year, $currency);
    }

    public function excludedPayoutsForYear(string $affiliateId, int $year, ?string $currency = null): array
    {
        return $this->payouts->excluded($affiliateId, $year, $currency);
    }

    public function affiliatesRequiring1099(int $year): array
    {
        return $this->payouts->affiliatesRequiring1099($year);
    }

    public function documentsFor(string $affiliateId, int $limit = 100): array
    {
        return $this->documents->forAffiliate($affiliateId, $limit);
    }

    public function markSent(string $documentId): TaxDocumentRecord
    {
        return $this->markSent->execute($documentId);
    }

    public function regenerate(string $documentId): TaxDocumentRecord
    {
        $document = AffiliateTaxDocument::query()->findOrFail($documentId);

        return $this->generate->execute($document->affiliate_id, $document->tax_year, $documentId)
            ?? throw new LogicException('Tax document disappeared during regeneration.');
    }
}
