<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Tax\Actions;

use AIArmada\Affiliates\Enums\TaxDocumentStatus;
use AIArmada\Affiliates\Features\Tax\Api\TaxDocumentRecord;
use AIArmada\Affiliates\Features\Tax\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Features\Tax\Queries\TaxDocumentQuery;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class MarkTaxDocumentSent
{
    public function execute(string $documentId): TaxDocumentRecord
    {
        return (new AffiliateTaxDocument)->getConnection()->transaction(function () use ($documentId): TaxDocumentRecord {
            $document = AffiliateTaxDocument::query()->whereKey($documentId)->lockForUpdate()->firstOrFail();

            if ($document->isSent()) {
                return TaxDocumentQuery::record($document);
            }

            if (! $document->isGenerated() || $document->document_path === null) {
                throw new InvalidArgumentException('Only a generated worksheet can be marked sent.');
            }

            $document->update(['status' => TaxDocumentStatus::Sent, 'sent_at' => CarbonImmutable::now()]);

            return TaxDocumentQuery::record($document);
        }, attempts: 3);
    }
}
