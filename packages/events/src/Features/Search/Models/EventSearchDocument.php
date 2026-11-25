<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Search\Models;

use AIArmada\Events\Database\Factories\EventSearchDocumentFactory;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\Events\Platform\ScopesByEventOwner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $searchable_type
 * @property string $searchable_id
 * @property string|null $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string $document_type
 * @property string|null $title
 * @property string|null $summary
 * @property string|null $body
 * @property array|null $keywords
 * @property array|null $facets
 * @property array|null $coordinates
 * @property CarbonImmutable|null $indexed_at
 * @property CarbonImmutable|null $stale_at
 * @property string $status
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $searchable
 * @property-read Model|null $event
 * @property-read Model|null $occurrence
 * @property-read Model|null $session
 */
final class EventSearchDocument extends Model
{
    use HasFactory;
    use HasUuids;
    use ScopesByEventOwner;

    protected $fillable = [
        'searchable_type', 'searchable_id',
        'event_id', 'event_occurrence_id', 'event_session_id',
        'document_type',
        'title', 'summary', 'body',
        'keywords', 'facets', 'coordinates',
        'indexed_at', 'stale_at',
        'status',
        'metadata',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('events.database.tables.event_search_documents', 'event_search_documents');
    }

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'facets' => 'array',
            'coordinates' => 'array',
            'indexed_at' => 'immutable_datetime',
            'stale_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function searchable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::eventClass(), 'event_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::occurrenceClass(), 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::sessionClass(), 'event_session_id');
    }

    protected static function newFactory(): EventSearchDocumentFactory
    {
        return EventSearchDocumentFactory::new();
    }
}
