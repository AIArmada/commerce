<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

/**
 * @property int $id
 * @property string $name
 * @property string $public_key
 * @property string $callback_url
 * @property string $email
 * @property array<string> $event_hooks
 */
class SendWebhook extends IntegerIdChipModel
{
    public $timestamps = true;

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
