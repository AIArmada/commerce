<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventAttendanceLogFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_attendance_id
 * @property string $action
 * @property string $source
 * @property string|null $performed_by_type
 * @property string|null $performed_by_id
 * @property CarbonImmutable $occurred_at
 * @property string|null $notes
 * @property array|null $metadata
 * @property Carbon $created_at
 */
final class EventAttendanceLog extends Model
{
    use HasFactory;
    use UsesEventUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'event_attendance_id',
        'action', 'source',
        'performed_by_type', 'performed_by_id',
        'occurred_at',
        'notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_attendance_logs', 'event_attendance_logs');
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EventAttendance, $this>
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(EventAttendance::class, 'event_attendance_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function performedBy(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): EventAttendanceLogFactory
    {
        return EventAttendanceLogFactory::new();
    }
}
