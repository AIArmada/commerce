<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Models;

final class ChipSendWebhook extends ChipModel
{
    public $timestamps = false;

    protected static function tableSuffix(): string
    {
        return 'send_webhooks';
    }

    protected function casts(): array
    {
        return [
            'event_hooks' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
