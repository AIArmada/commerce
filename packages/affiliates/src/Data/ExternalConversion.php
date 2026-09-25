<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

/**
 * An externally-attributed conversion awaiting merchant books.
 *
 * The external system computed commission and currency; the merchant
 * ledger only persists, guards, and accounts. All math arrives resolved.
 */
final readonly class ExternalConversion
{
    public function __construct(
        public string $source,
        public string $sourceRef,
        public string $affiliateId,
        public int $revenueMinor,
        public string $currency,
        public string $externalReference,
        public int $commissionMinor,
        public ?string $affiliateEmail = null,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}
}
