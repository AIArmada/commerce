<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Api;

interface WebhookReplay
{
    /** @return 'replayed'|'skipped'|'failed' */
    public function replay(string $eventId, bool $force): string;
}
