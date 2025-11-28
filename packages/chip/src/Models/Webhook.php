<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

/**
 * @property string|null $url
 * @property array<string>|null $events
 * @property array<string, mixed>|null $payload
 * @property array<string, string>|null $headers
 * @property bool $all_events
 * @property bool $verified
 * @property bool $processed
 * @property int|null $created_on
 * @property int|null $updated_on
 */
class Webhook extends ChipModel
{
    public $timestamps = true;

    /** @return Attribute<Carbon|null, never> */
    public function createdOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['created_on'] ?? null));
    }

    /** @return Attribute<Carbon|null, never> */
    public function updatedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['updated_on'] ?? null));
    }

    protected static function tableSuffix(): string
    {
        return 'webhooks';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'payload' => 'array',
            'headers' => 'array',
            'all_events' => 'boolean',
            'verified' => 'boolean',
            'processed' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
