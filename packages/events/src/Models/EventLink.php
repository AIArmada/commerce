<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventLinkFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string $link_type
 * @property string $label
 * @property string $url
 * @property string $visibility
 * @property CarbonImmutable|null $opens_at
 * @property CarbonImmutable|null $expires_at
 * @property string|null $access_notes
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventLink extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'link_type', 'label', 'url',
        'visibility',
        'opens_at', 'expires_at', 'access_notes',
        'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_links', 'event_links');
    }

    protected function casts(): array
    {
        return [
            'opens_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    protected static function newFactory(): EventLinkFactory
    {
        return EventLinkFactory::new();
    }
}
