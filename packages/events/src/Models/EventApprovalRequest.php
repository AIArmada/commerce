<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventApprovalRequestFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $approvable_type
 * @property string $approvable_id
 * @property string|null $target_type
 * @property string|null $target_id
 * @property string|null $requested_by_type
 * @property string|null $requested_by_id
 * @property string|null $assigned_to_type
 * @property string|null $assigned_to_id
 * @property string $status
 * @property string|null $reason
 * @property string|null $notes
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $rejected_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventApprovalRequest extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'approvable_type', 'approvable_id',
        'target_type', 'target_id',
        'requested_by_type', 'requested_by_id',
        'assigned_to_type', 'assigned_to_id',
        'status', 'reason', 'notes',
        'approved_at', 'rejected_at',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_approval_requests', 'event_approval_requests');
    }

    protected function casts(): array
    {
        return [
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function requestedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function assignedTo(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): EventApprovalRequestFactory
    {
        return EventApprovalRequestFactory::new();
    }
}
