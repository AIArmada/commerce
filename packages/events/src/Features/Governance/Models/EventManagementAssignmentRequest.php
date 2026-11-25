<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Models;

use AIArmada\Events\Data\AssignmentRequestStatus;
use AIArmada\Events\Platform\ScopesByEventOwner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

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
 * @property-read Model|null $manageable
 * @property-read Model|null $requestor
 * @property-read Model|null $reviewer
 */
final class EventManagementAssignmentRequest extends Model
{
    use HasFactory;
    use HasUuids;
    use ScopesByEventOwner;

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
        return $this->table ?? config('events.database.tables.event_management_assignment_requests', 'event_management_assignment_requests');
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

    /**
     * @return MorphTo<Model, $this>
     */
    public function manageable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function eventOwnerRelation(): ?string
    {
        return null;
    }

    protected static function eventOwnerMorphRelation(): ?string
    {
        return 'manageable';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function requestor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reviewer(): MorphTo
    {
        return $this->morphTo();
    }
}
