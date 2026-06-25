<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Enums\FeedbackFormVisibility;
use AIArmada\Feedback\Models\Concerns\UsesFeedbackUuid;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string|null $slug
 * @property string $purpose
 * @property FeedbackFormStatus $status
 * @property FeedbackFormVisibility $visibility
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property bool $is_anonymous_allowed
 * @property bool $is_anonymity_optional
 * @property bool $is_login_required
 * @property bool $is_one_response_per_respondent
 * @property bool $is_edit_after_submit_allowed
 * @property CarbonImmutable|null $opens_at
 * @property CarbonImmutable|null $closes_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $archived_at
 * @property array|null $settings
 * @property array|null $metadata
 * @property string|null $created_by_type
 * @property string|null $created_by_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Model|Eloquent $owner
 * @property-read Model|Eloquent $subject
 * @property-read Model|Eloquent $createdBy
 * @property-read Collection<int, FeedbackSection> $sections
 * @property-read Collection<int, FeedbackQuestion> $questions
 * @property-read Collection<int, FeedbackResponse> $responses
 * @property-read Collection<int, FeedbackInvitation> $invitations
 */
final class FeedbackForm extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesFeedbackUuid;

    protected static string $ownerScopeConfigKey = 'feedback.owner';

    protected $fillable = [
        'name', 'slug', 'purpose', 'status', 'visibility',
        'subject_type', 'subject_id',
        'is_anonymous_allowed', 'is_anonymity_optional',
        'is_login_required', 'is_one_response_per_respondent',
        'is_edit_after_submit_allowed',
        'opens_at', 'closes_at',
        'published_at', 'closed_at', 'archived_at',
        'settings', 'metadata',
        'created_by_type', 'created_by_id',
    ];

    public function getTable(): string
    {
        $prefix = (string) config('feedback.database.table_prefix', '');

        return $prefix . (string) config('feedback.database.tables.forms', 'feedback_forms');
    }

    protected function casts(): array
    {
        return [
            'status' => FeedbackFormStatus::class,
            'visibility' => FeedbackFormVisibility::class,
            'is_anonymous_allowed' => 'boolean',
            'is_anonymity_optional' => 'boolean',
            'is_login_required' => 'boolean',
            'is_one_response_per_respondent' => 'boolean',
            'is_edit_after_submit_allowed' => 'boolean',
            'settings' => 'array',
            'metadata' => 'array',
            'opens_at' => 'immutable_datetime',
            'closes_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(FeedbackSection::class, 'feedback_form_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FeedbackQuestion::class, 'feedback_form_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FeedbackResponse::class, 'feedback_form_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(FeedbackInvitation::class, 'feedback_form_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('subject');
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo('created_by');
    }
}
