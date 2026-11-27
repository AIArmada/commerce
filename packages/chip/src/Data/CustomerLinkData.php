<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use AIArmada\CommerceSupport\Data\RecordReference;

final readonly class CustomerLinkData
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $id,
        public RecordReference $subject,
        public string $chip_customer_id,
        public ?RecordReference $owner,
        public array $metadata,
    ) {}
}
