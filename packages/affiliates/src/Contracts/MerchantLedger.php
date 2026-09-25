<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Data\ExternalConversion;
use AIArmada\Affiliates\Data\PostedConversion;

/**
 * Merchant ledger seam.
 *
 * Records externally-attributed conversions (marketplace drafts, imported
 * sales) into merchant books. Merchant vocabulary throughout: the engine
 * never names the external system beyond its opaque source key.
 */
interface MerchantLedger
{
    /**
     * Post one external conversion. Idempotent on (source, source ref):
     * redeliveries return the existing posting without new rows.
     */
    public function postExternalConversion(ExternalConversion $draft): ?PostedConversion;

    public function findPosted(string $source, string $sourceRef): ?PostedConversion;

    public function findPosting(string $source, string $sourceRef, string $externalReference): ?PostedConversion;

    /**
     * @return array<int, array<string, mixed>> Postings for reconciliation.
     */
    public function postingsForSourceRef(string $source, string $sourceRef): array;

    /**
     * Postings sharing one external reference, across all origins.
     *
     * Feeds the dual-reporting collision report: the same sale posted
     * by two origins (or two source refs) means two systems paid it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function postingsForExternalReference(string $externalReference): array;
}
