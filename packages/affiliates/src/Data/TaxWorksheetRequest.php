<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

final readonly class TaxWorksheetRequest
{
    public function __construct(
        public string $affiliateId,
        public int $year,
        public int $amountMinor,
        public string $currency,
        public TaxRecipientDetails $recipient,
        public ?string $excludedNotes = null,
    ) {}
}
