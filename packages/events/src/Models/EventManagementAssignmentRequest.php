<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Enums\AssignmentRequestStatus;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;

/**
 * @property string $id
 * @property string $manageable_type
 * @property string $manageable_id
 * @property string $requestor_type
 * @property string $requestor_id
 * @property string|null $reviewer_type
 * @property string|null $reviewer_id
 * @property AssignmentRequestStatus $status
 * @property string|null $requested_role
 * @property string|null $justification
 * @property string|null $reviewer_note
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $manageable
 * @property-read Model|Eloquent $requestor
 * @property-read Model|Eloquent|null $reviewer
 */
final class EventManagementAssignmentRequest extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'manageable_type', 'manageable_id',
        'requestor_type', 'requestor_id',
        'reviewer_type', 'reviewer_id',
        'status', 'requested_role',
        'justification', 'reviewer_note',
        'reviewed_at', 'cancelled_at',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_management_assignment_requests', 'event_management_assignment_requests');
    }

    protected function casts(): array
    {
        return [
            'status' => AssignmentRequestStatus::class,
            'reviewed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function manageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requestor(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewer(): MorphTo
    {
        return $this->morphTo();
    }
}
