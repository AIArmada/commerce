<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventEngagementFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $event_attendance_id
 * @property string $engagement_type
 * @property array|null $metadata
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read EventAttendance $attendance
 */
final class EventEngagement extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_attendance_id',
        'engagement_type',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_engagements', 'event_engagements');
    }

    protected function casts(): array
    {
        return [
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

    protected static function newFactory(): EventEngagementFactory
    {
        return EventEngagementFactory::new();
    }
}
