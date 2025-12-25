<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

/**
 * @property string|null $status
 * @property bool $is_test
 * @property int|null $created_on
 * @property int|null $updated_on
 * @property int|null $began_on
 * @property int|null $finished_on
 * @property-read Carbon|null $createdOn
 * @property-read Carbon|null $updatedOn
 * @property-read Carbon|null $beganOn
 * @property-read Carbon|null $finishedOn
 */
class CompanyStatement extends ChipModel
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

    /** @return Attribute<Carbon|null, never> */
    public function beganOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['began_on'] ?? null));
    }

    /** @return Attribute<Carbon|null, never> */
    public function finishedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?Carbon => $this->toTimestamp($attributes['finished_on'] ?? null));
    }

    public function statusColor(): string
    {
        $status = $this->status ?? '';

        return match ($status) {
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_test' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
