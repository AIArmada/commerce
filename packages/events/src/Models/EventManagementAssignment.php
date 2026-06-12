<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventManagementAssignmentFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string $manageable_type
 * @property string $manageable_id
 * @property string|null $manager_type
 * @property string|null $manager_id
 * @property string|null $assigned_by_type
 * @property string|null $assigned_by_id
 * @property string $role
 * @property array|null $permissions
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event|null $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read EventSession|null $session
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $manageable
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $manager
 */
final class EventManagementAssignment extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'manageable_type', 'manageable_id',
        'manager_type', 'manager_id',
        'assigned_by_type', 'assigned_by_id',
        'role', 'permissions',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_management_assignments', 'event_management_assignments');
    }

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return BelongsTo<EventOccurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<EventSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    protected static function newFactory(): EventManagementAssignmentFactory
    {
        return EventManagementAssignmentFactory::new();
    }
}
