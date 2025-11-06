<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

final class ChipWebhook extends ChipModel
{
    public $incrementing = false;

    public $timestamps = true;

    protected $keyType = 'string';

    public function createdOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['created_on'] ?? null));
    }

    public function updatedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['updated_on'] ?? null));
    }

    protected static function tableSuffix(): string
    {
        return 'webhooks';
    }

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
