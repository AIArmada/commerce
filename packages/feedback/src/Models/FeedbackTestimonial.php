<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Contacting\Concerns\HasContactMethods;
use AIArmada\Contacting\Concerns\HasSocialProfiles;
use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Models\Concerns\UsesFeedbackUuid;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $feedback_response_id
 * @property string|null $feedback_answer_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $respondent_type
 * @property string|null $respondent_id
 * @property string $quote
 * @property string|null $display_name
 * @property string|null $display_title
 * @property string|null $display_organization
 * @property float|null $rating
 * @property FeedbackTestimonialStatus $status
 * @property CarbonImmutable|null $permission_given_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $hidden_at
 * @property array|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read FeedbackResponse|null $response
 * @property-read FeedbackAnswer|null $answer
 * @property-read Model|Eloquent $subject
 * @property-read Model|Eloquent $respondent
 */
final class FeedbackTestimonial extends Model
{
    use HasContactMethods;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasSocialProfiles;
    use UsesFeedbackUuid;

    protected static string $ownerScopeConfigKey = 'feedback.owner';

    protected $fillable = [
        'owner_type', 'owner_id',
        'feedback_response_id', 'feedback_answer_id',
        'subject_type', 'subject_id',
        'respondent_type', 'respondent_id',
        'quote', 'display_name', 'display_title', 'display_organization',
        'rating', 'status',
        'permission_given_at', 'approved_at', 'rejected_at',
        'published_at', 'hidden_at',
        'metadata',
    ];

    public function getTable(): string
    {
        $prefix = (string) config('feedback.database.table_prefix', '');

        return $prefix . (string) config('feedback.database.tables.testimonials', 'feedback_testimonials');
    }

    protected function casts(): array
    {
        return [
            'status' => FeedbackTestimonialStatus::class,
            'rating' => 'decimal:2',
            'metadata' => 'array',
            'permission_given_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'hidden_at' => 'immutable_datetime',
        ];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(FeedbackResponse::class, 'feedback_response_id');
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(FeedbackAnswer::class, 'feedback_answer_id');
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
