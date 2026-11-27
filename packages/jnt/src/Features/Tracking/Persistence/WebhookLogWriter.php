<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Persistence;

use AIArmada\Jnt\Features\Tracking\Models\JntWebhookLog;

final readonly class WebhookLogWriter
{
    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): void
    {
        JntWebhookLog::query()->withoutOwnerScope()->whereKey($id)->update($attributes);
    }
}
