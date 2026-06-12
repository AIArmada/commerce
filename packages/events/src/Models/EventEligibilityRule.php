<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventEligibilityRuleFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string $rule_type
 * @property string|null $operator
 * @property string|null $value
 * @property mixed|null $value_json
 * @property string|null $effect
 * @property string|null $message
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventEligibilityRule extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'rule_type', 'operator', 'value', 'value_json',
        'effect', 'message',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_eligibility_rules', 'event_eligibility_rules');
    }

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): EventEligibilityRuleFactory
    {
        return EventEligibilityRuleFactory::new();
    }
}
