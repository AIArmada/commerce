<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

/**
 * @property string|null $url
 * @property array<string>|null $event_hooks
 */
class SendWebhook extends ChipModel
{
    public $timestamps = false;

    protected static function tableSuffix(): string
    {
        return 'send_webhooks';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_hooks' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
