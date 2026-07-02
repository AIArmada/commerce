<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Models;

use AIArmada\Ticketing\Database\Factories\PassHolderFactory;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $pass_id
 * @property string|null $holder_type
 * @property string|null $holder_id
 * @property string|null $name
 * @property string|null $email
 * @property bool $is_current
 * @property CarbonImmutable|null $transferred_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pass $pass
 * @property-read Model|Eloquent|null $holder
 */
class PassHolder extends Model
{
    use HasFactory;
    use HasUuids;

    protected static function newFactory(): PassHolderFactory
    {
        return PassHolderFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = [
        'is_current' => true,
    ];

    protected $fillable = [
        'pass_id',
        'holder_type',
        'holder_id',
        'name',
        'email',
        'is_current',
        'transferred_at',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('ticketing.database.tables.pass_holders', 'pass_holders');
    }

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'transferred_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Pass, $this>
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function holder(): MorphTo
    {
        return $this->morphTo();
    }
}
