<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

use AIArmada\Affiliates\Data\AffiliateEventReference;

final readonly class AttributionSignalRecord
{
    public function __construct(
        public AffiliateEventReference $reference,
        public ?string $subjectKey,
        public ?string $subjectInstance,
        public ?string $cartIdentifier,
        public string $cartInstance,
        public ?string $cookieValue,
        public ?string $voucherCode,
        public ?string $userId,
        public ?string $landingUrl,
        public ?string $referrerUrl,
        public ?string $source,
        public ?string $medium,
        public ?string $campaign,
        public ?string $content,
        public ?string $term,
        public ?string $occurredAt,
    ) {}
}
