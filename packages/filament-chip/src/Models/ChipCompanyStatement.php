<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property string $status
 */
final class ChipCompanyStatement extends ChipModel
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

    public function beganOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['began_on'] ?? null));
    }

    public function finishedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?\Illuminate\Support\Carbon => $this->toTimestamp($attributes['finished_on'] ?? null));
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'completed', 'ready' => 'success',
            'queued', 'processing' => 'warning',
            'failed', 'expired' => 'danger',
            default => 'gray',
        };
    }

    protected static function tableSuffix(): string
    {
        return 'company_statements';
    }

    protected function casts(): array
    {
        return [
            'is_test' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
