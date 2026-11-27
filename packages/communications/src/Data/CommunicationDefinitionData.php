<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

final readonly class CommunicationDefinitionData
{
    public function __construct(
        public string $notifiableClass,
        public string $notificationClass,
        public array $channels = [],
        public array $recipientIds = [],
        public array $content = [],
        public string | null $locale = null,
        public string | null $timezone = null,
    ) {}
}
