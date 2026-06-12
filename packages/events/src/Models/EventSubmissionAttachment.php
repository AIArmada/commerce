<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventSubmissionAttachmentFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_submission_id
 * @property string|null $file_id
 * @property string|null $url
 * @property string $name
 * @property string|null $mime_type
 * @property int|null $size
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventSubmissionAttachment extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_submission_id',
        'file_id', 'url', 'name', 'mime_type', 'size',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_submission_attachments', 'event_submission_attachments');
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
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

    protected static function newFactory(): EventSubmissionAttachmentFactory
    {
        return EventSubmissionAttachmentFactory::new();
    }
}
