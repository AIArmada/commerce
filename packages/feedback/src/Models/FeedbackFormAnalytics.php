<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Feedback\Data\FeedbackAnalyticsData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $feedback_form_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property int $total_responses
 * @property int $completed_responses
 * @property float|null $average_score
 * @property float|null $max_score
 * @property float $completion_rate
 * @property int $pending_review
 * @property int $rejected
 * @property int $spam
 * @property CarbonImmutable $calculated_at
 */
final class FeedbackFormAnalytics extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'feedback.owner';

    protected $fillable = [
        'feedback_form_id',
        'total_responses',
        'completed_responses',
        'average_score',
        'max_score',
        'completion_rate',
        'pending_review',
        'rejected',
        'spam',
        'calculated_at',
    ];

    public function getTable(): string
    {
        $prefix = (string) config('feedback.database.table_prefix', '');

        return $prefix . (string) config('feedback.database.tables.form_analytics', 'feedback_form_analytics');
    }

    /**
     * @return BelongsTo<FeedbackForm, FeedbackFormAnalytics>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(FeedbackForm::class, 'feedback_form_id');
    }

    public function toData(): FeedbackAnalyticsData
    {
        return new FeedbackAnalyticsData(
            totalResponses: (int) $this->total_responses,
            completedResponses: (int) $this->completed_responses,
            averageScore: $this->average_score !== null ? (float) $this->average_score : null,
            maxScore: $this->max_score !== null ? (float) $this->max_score : null,
            completionRate: (float) $this->completion_rate,
            pendingReview: (int) $this->pending_review,
            rejected: (int) $this->rejected,
            spam: (int) $this->spam,
        );
    }

    protected function casts(): array
    {
        return [
            'total_responses' => 'integer',
            'completed_responses' => 'integer',
            'average_score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'completion_rate' => 'decimal:2',
            'pending_review' => 'integer',
            'rejected' => 'integer',
            'spam' => 'integer',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
