<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

final readonly class RenderedContentData
{
    public function __construct(
        public string $channel,
        public ?string $locale = null,
        public ?string $subject = null,
        public ?string $contentText = null,
        public ?string $contentHtml = null,
        public array $payload = [],
        public ?string $checksum = null,
        public ?string $templateId = null,
        public ?string $templateVersionId = null,
    ) {}
}
