<?php

declare(strict_types=1);

namespace AIArmada\Communications\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TemplatePublished
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $templateId,
        public readonly string $versionId,
        public readonly int $version,
        public readonly string $channel,
    ) {}
}
