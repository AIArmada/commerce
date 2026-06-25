<?php

declare(strict_types=1);

namespace AIArmada\Membership\Models;

use AIArmada\Membership\Enums\ApplicationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $applicant_id
 * @property ApplicationStatus $status
 * @property string|null $granted_role
 * @property string $justification
 * @property string|null $reviewer_id
 * @property string|null $reviewer_note
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property array|null $meta
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class MembershipApplication extends Model
{
    use HasUuids;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'applicant_id',
        'status',
        'granted_role',
        'justification',
        'reviewer_id',
        'reviewer_note',
        'reviewed_at',
        'cancelled_at',
        'meta',
    ];

    public function getTable(): string
    {
        return (string) config('membership.database.tables.applications', 'membership_applications');
    }

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'meta' => 'array',
            'reviewed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function applicant(): BelongsTo
    {
        /** @phpstan-ignore argument.templateType */
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function reviewer(): BelongsTo
    {
        /** @phpstan-ignore argument.templateType */
        return $this->belongsTo(config('auth.providers.users.model'), 'reviewer_id');
    }
}
