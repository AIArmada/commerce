<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class CommunicationDefinitionData extends Data
{
    public function __construct(
        public readonly string $notifiableClass,
        public readonly string $notificationClass,
        public readonly array $channels = [],
        public readonly array $recipientIds = [],
        public readonly array $content = [],
        public readonly string | null | Optional $locale = null,
        public readonly string | null | Optional $timezone = null,
    ) {}
}
