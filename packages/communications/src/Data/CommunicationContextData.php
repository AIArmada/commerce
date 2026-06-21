<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class CommunicationContextData extends Data
{
    public function __construct(
        public readonly CommunicationDirection $direction = CommunicationDirection::Outbound,
        public readonly CommunicationCategory $category = CommunicationCategory::Transactional,
        public readonly CommunicationPriority $priority = CommunicationPriority::Normal,
        public readonly string | null | Optional $purpose = null,
        public readonly string | null | Optional $subjectType = null,
        public readonly string | null | Optional $subjectId = null,
        public readonly string | null | Optional $senderType = null,
        public readonly string | null | Optional $senderId = null,
        public readonly string | null | Optional $batchId = null,
        public readonly string | null | Optional $threadId = null,
        public readonly string | null | Optional $parentId = null,
        public readonly string | null | Optional $locale = null,
        public readonly string | null | Optional $timezone = null,
        public readonly string | null | Optional $idempotencyKey = null,
        public readonly string | null | Optional $scheduledAt = null,
        public readonly string | null | Optional $expiresAt = null,
        public readonly array $metadata = [],
    ) {}
}
