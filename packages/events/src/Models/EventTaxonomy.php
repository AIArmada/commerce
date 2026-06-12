<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventTaxonomyFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_hierarchical
 * @property bool $is_active
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventTaxonomy extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'code', 'name', 'description',
        'is_hierarchical', 'is_active',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_taxonomies', 'event_taxonomies');
    }

    protected function casts(): array
    {
        return [
            'is_hierarchical' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): EventTaxonomyFactory
    {
        return EventTaxonomyFactory::new();
    }
}
