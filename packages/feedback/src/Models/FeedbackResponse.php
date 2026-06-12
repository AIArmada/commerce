<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Contacting\Concerns\HasContactMethods;
use AIArmada\Contacting\Concerns\HasSocialProfiles;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Models\Concerns\UsesFeedbackUuid;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $feedback_form_id
 * @property string|null $feedback_invitation_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $respondent_type
 * @property string|null $respondent_id
 * @property FeedbackResponseStatus $status
 * @property bool $is_anonymous
 * @property bool $is_editable
 * @property float|null $score
 * @property float|null $max_score
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $marked_spam_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read FeedbackForm $form
 * @property-read FeedbackInvitation|null $invitation
 * @property-read Collection<int, FeedbackAnswer> $answers
 * @property-read Model|Eloquent $subject
 * @property-read Model|Eloquent $respondent
 */
final class FeedbackResponse extends Model
{
    use HasContactMethods;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasSocialProfiles;
    use UsesFeedbackUuid;

    protected static string $ownerScopeConfigKey = 'feedback.owner';

    protected $fillable = [
        'owner_type', 'owner_id',
        'feedback_form_id', 'feedback_invitation_id',
        'subject_type', 'subject_id',
        'respondent_type', 'respondent_id',
        'status', 'is_anonymous', 'is_editable',
        'score', 'max_score',
        'started_at', 'submitted_at', 'reviewed_at', 'rejected_at', 'marked_spam_at',
        'ip_address', 'user_agent',
        'metadata',
    ];

    public function getTable(): string
    {
        $prefix = (string) config('feedback.database.table_prefix', '');

        return $prefix . (string) config('feedback.database.tables.responses', 'feedback_responses');
    }

    protected function casts(): array
    {
        return [
            'status' => FeedbackResponseStatus::class,
            'is_anonymous' => 'boolean',
            'is_editable' => 'boolean',
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'metadata' => 'array',
            'started_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'marked_spam_at' => 'immutable_datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(FeedbackForm::class, 'feedback_form_id');
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(FeedbackInvitation::class, 'feedback_invitation_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(FeedbackAnswer::class, 'feedback_response_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('subject');
    }

    public function respondent(): MorphTo
    {
        return $this->morphTo('respondent');
    }
}
