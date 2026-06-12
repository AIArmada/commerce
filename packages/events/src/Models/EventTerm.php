<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventTermFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    protected static function newFactory(): EventTermFactory
    {
        return EventTermFactory::new();
    }
}
