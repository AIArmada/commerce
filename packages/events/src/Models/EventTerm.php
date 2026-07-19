<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventTermFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventTermPolicy> $policies
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
        return $this->belongsTo(self::class, 'parent_id')
            ->where($this->getTable() . '.event_taxonomy_id', $this->event_taxonomy_id);
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where($this->getTable() . '.event_taxonomy_id', $this->event_taxonomy_id)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /** @return HasMany<EventTermPolicy, $this> */
    public function policies(): HasMany
    {
        return $this->hasMany(EventTermPolicy::class, 'event_term_id');
    }

    /** @param Builder<self> $query */
    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<self> $query */
    public function scopeRoots($query): void
    {
        $query->whereNull('parent_id');
    }

    protected static function newFactory(): EventTermFactory
    {
        return EventTermFactory::new();
    }
}
