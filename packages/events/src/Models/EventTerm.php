<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventTermFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $event_taxonomy_id
 * @property string|null $parent_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventTerm extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_taxonomy_id', 'parent_id',
        'code', 'name', 'description',
        'sort_order', 'is_active',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_terms', 'event_terms');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<EventTaxonomy, $this> */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(EventTaxonomy::class, 'event_taxonomy_id');
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /** @param \Illuminate\Database\Eloquent\Builder<self> $query */
    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    /** @param \Illuminate\Database\Eloquent\Builder<self> $query */
    public function scopeRoots($query): void
    {
        $query->whereNull('parent_id');
    }

    protected static function newFactory(): EventTermFactory
    {
        return EventTermFactory::new();
    }
}
