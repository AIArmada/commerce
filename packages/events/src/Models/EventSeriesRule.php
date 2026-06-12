<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventSeriesRuleFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_series_id
 * @property string $rule_type
 * @property string|null $operator
 * @property string|null $value
 * @property mixed|null $value_json
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventSeriesRule extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_series_id',
        'rule_type', 'operator', 'value', 'value_json',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_series_rules', 'event_series_rules');
    }

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): EventSeriesRuleFactory
    {
        return EventSeriesRuleFactory::new();
    }
}
