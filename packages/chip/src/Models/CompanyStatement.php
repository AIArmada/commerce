<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property string|null $status
 * @property bool $is_test
 * @property int|null $created_on
 * @property int|null $updated_on
 * @property int|null $began_on
 * @property int|null $finished_on
 * @property-read CarbonImmutable|null $createdOn
 * @property-read CarbonImmutable|null $updatedOn
 * @property-read CarbonImmutable|null $beganOn
 * @property-read CarbonImmutable|null $finishedOn
 */
class CompanyStatement extends ChipModel
{
    public $timestamps = true;

    public function createdOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?CarbonImmutable => $this->toTimestamp($attributes['created_on'] ?? null));
    }

    public function updatedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?CarbonImmutable => $this->toTimestamp($attributes['updated_on'] ?? null));
    }

    public function beganOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?CarbonImmutable => $this->toTimestamp($attributes['began_on'] ?? null));
    }

    public function finishedOn(): Attribute
    {
        return Attribute::get(fn (?int $value, array $attributes): ?CarbonImmutable => $this->toTimestamp($attributes['finished_on'] ?? null));
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
