<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventTemplateItemFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_template_id
 * @property string $item_type
 * @property string|null $item_key
 * @property array $payload
 * @property int $sort_order
 * @property string $status
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EventTemplate $template
 */
final class EventTemplateItem extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_template_id',
        'item_type', 'item_key',
        'payload',
        'sort_order', 'status',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_template_items', 'event_template_items');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'payload' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EventTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EventTemplate::class, 'event_template_id');
    }

    protected static function newFactory(): EventTemplateItemFactory
    {
        return EventTemplateItemFactory::new();
    }
}
