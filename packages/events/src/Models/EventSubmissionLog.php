<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventSubmissionLogFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_submission_id
 * @property string $action
 * @property string|null $performed_by_type
 * @property string|null $performed_by_id
 * @property string|null $notes
 * @property array|null $metadata
 * @property Carbon $created_at
 */
final class EventSubmissionLog extends Model
{
    use HasFactory;
    use UsesEventUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'event_submission_id',
        'action',
        'performed_by_type', 'performed_by_id',
        'notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_submission_logs', 'event_submission_logs');
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EventSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(EventSubmission::class, 'event_submission_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function performedBy(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): EventSubmissionLogFactory
    {
        return EventSubmissionLogFactory::new();
    }
}
