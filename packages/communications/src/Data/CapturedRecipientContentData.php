<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

final readonly class CapturedRecipientContentData
{
    public function __construct(
        public string $recipientId,
        public string $contentId,
    ) {}
}
