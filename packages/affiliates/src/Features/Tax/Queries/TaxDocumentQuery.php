<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Tax\Queries;

use AIArmada\Affiliates\Features\Tax\Api\TaxDocumentRecord;
use AIArmada\Affiliates\Features\Tax\Models\AffiliateTaxDocument;

final class TaxDocumentQuery
{
    /** @return list<TaxDocumentRecord> */
    public function forAffiliate(string $affiliateId, int $limit): array
    {
        return AffiliateTaxDocument::query()->where('affiliate_id', $affiliateId)->orderByDesc('tax_year')->limit(max(1, min(100, $limit)))->get()
            ->map(self::record(...))->values()->all();
    }

    public static function record(AffiliateTaxDocument $document): TaxDocumentRecord
    {
        return new TaxDocumentRecord(
            (string) $document->getKey(),
            $document->affiliate_id,
            $document->document_type,
            $document->tax_year,
            $document->status,
            $document->total_amount_minor,
            $document->currency,
            $document->notes,
            $document->generated_at?->toDateTimeString(),
            $document->sent_at?->toDateTimeString(),
        );
    }
}
