<?php

declare(strict_types=1);

namespace AIArmada\Membership\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Support\MembershipUserModel;
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
 * @property string|null $cancelled_by
 * @property array|null $meta
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class MembershipApplication extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'applicant_id',
        'justification',
        'meta',
    ];

    protected $hidden = ['pending_guard'];

    protected static string $ownerScopeConfigKey = 'membership.owner';

    protected static bool $ownerScopeEnabledByDefault = true;

    protected static function booted(): void
    {
        static::creating(function (self $application): void {
            $application->status ??= ApplicationStatus::Pending;
        });
    }

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
        return $this->belongsTo(MembershipUserModel::resolve());
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(MembershipUserModel::resolve(), 'reviewer_id');
    }
}
