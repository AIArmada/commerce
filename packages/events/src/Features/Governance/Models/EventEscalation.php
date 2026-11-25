<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Models;

use AIArmada\Events\Data\EventEscalationType;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\Events\Platform\ScopesByEventOwner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property EventEscalationType $type
 * @property string $decision_key
 * @property string|null $reason
 * @property CarbonImmutable|null $dispatched_at
 * @property CarbonImmutable|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $event
 */
final class EventEscalation extends Model
{
    use HasFactory;
    use HasUuids;
    use ScopesByEventOwner;

    protected $fillable = [
        'event_id',
        'type',
        'decision_key',
        'reason',
        'dispatched_at',
        'resolved_at',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('events.database.tables.event_escalations', 'event_escalations');
    }

    protected function casts(): array
    {
        return [
            'type' => EventEscalationType::class,
            'dispatched_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::eventClass());
    }
}
