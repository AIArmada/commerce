<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

final readonly class RenderedContentData
{
    public function __construct(
        public string $channel,
        public string | null $locale = null,
        public string | null $subject = null,
        public string | null $contentText = null,
        public string | null $contentHtml = null,
        public array $payload = [],
        public string | null $checksum = null,
        public string | null $templateId = null,
        public string | null $templateVersionId = null,
    ) {}
}
