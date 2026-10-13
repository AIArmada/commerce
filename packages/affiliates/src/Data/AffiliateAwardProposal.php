<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

/**
 * Validated award proposal handed to the host authorization boundary.
 *
 * Every field is already bounded and validated by the award action before
 * this object is built, so host implementations can rely on its shape:
 * a non-empty caller-owned semantic key, a positive minor-unit amount, an
 * uppercase ISO-4217 currency, a creation-allowed status class, and
 * sanitized optional references.
 */
final readonly class AffiliateAwardProposal
{
    public function __construct(
        public string $awardKey,
        public int $amountMinor,
        public string $currency,
        /** @var class-string */
        public string $statusClass,
        public ?string $externalReference = null,
        public ?string $sourceRef = null,
    ) {}
}
