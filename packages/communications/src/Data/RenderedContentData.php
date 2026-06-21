<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class RenderedContentData extends Data
{
    public function __construct(
        public readonly string $channel,
        public readonly string | null | Optional $locale = null,
        public readonly string | null | Optional $subject = null,
        public readonly string | null | Optional $contentText = null,
        public readonly string | null | Optional $contentHtml = null,
        public readonly array $payload = [],
        public readonly string | null | Optional $checksum = null,
        public readonly string | null | Optional $templateId = null,
        public readonly string | null | Optional $templateVersionId = null,
    ) {}
}
