<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Feedback\Models\Concerns\UsesFeedbackUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $feedback_response_id
 * @property string $feedback_question_id
 * @property mixed $value
 * @property string|null $text_value
 * @property float|null $number_value
 * @property bool|null $boolean_value
 * @property string|null $date_value
 * @property CarbonImmutable|null $datetime_value
 * @property float|null $score
 * @property array|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read FeedbackResponse $response
 * @property-read FeedbackQuestion $question
 */
final class FeedbackAnswer extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesFeedbackUuid;

    protected static string $ownerScopeConfigKey = 'feedback.owner';

    protected $fillable = [
        'owner_type', 'owner_id',
        'feedback_response_id', 'feedback_question_id',
        'value', 'text_value', 'number_value', 'boolean_value',
        'date_value', 'datetime_value',
        'score',
        'metadata',
    ];

    public function getTable(): string
    {
        $prefix = (string) config('feedback.database.table_prefix', '');

        return $prefix . (string) config('feedback.database.tables.answers', 'feedback_answers');
    }

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'number_value' => 'decimal:4',
            'boolean_value' => 'boolean',
            'score' => 'decimal:2',
            'metadata' => 'array',
            'date_value' => 'date:Y-m-d',
            'datetime_value' => 'immutable_datetime',
        ];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(FeedbackResponse::class, 'feedback_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(FeedbackQuestion::class, 'feedback_question_id');
    }
}
