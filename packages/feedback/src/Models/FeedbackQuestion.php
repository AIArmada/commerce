<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Feedback\Models\Concerns\UsesFeedbackUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $feedback_form_id
 * @property string|null $feedback_section_id
 * @property string $key
 * @property string $type
 * @property string $label
 * @property string|null $description
 * @property string|null $help_text
 * @property string|null $placeholder
 * @property bool $is_required
 * @property bool $is_scored
 * @property int $order_column
 * @property array|null $validation_rules
 * @property array|null $visibility_rules
 * @property array|null $scoring_rules
 * @property array|null $settings
 * @property array|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read FeedbackForm $form
 * @property-read FeedbackSection|null $section
 * @property-read Collection<int, FeedbackQuestionOption> $options
 * @property-read Collection<int, FeedbackAnswer> $answers
 */
final class FeedbackQuestion extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesFeedbackUuid;

    protected static string $ownerScopeConfigKey = 'feedback.owner';

    protected $fillable = [
        'feedback_form_id', 'feedback_section_id',
        'key', 'type', 'label', 'description', 'help_text', 'placeholder',
        'is_required', 'is_scored', 'order_column',
        'validation_rules', 'visibility_rules', 'scoring_rules',
        'settings', 'metadata',
    ];

    public function getTable(): string
    {
        $prefix = (string) config('feedback.database.table_prefix', '');

        return $prefix . (string) config('feedback.database.tables.questions', 'feedback_questions');
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_scored' => 'boolean',
            'order_column' => 'integer',
            'validation_rules' => 'array',
            'visibility_rules' => 'array',
            'scoring_rules' => 'array',
            'settings' => 'array',
            'metadata' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(FeedbackForm::class, 'feedback_form_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FeedbackSection::class, 'feedback_section_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(FeedbackQuestionOption::class, 'feedback_question_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(FeedbackAnswer::class, 'feedback_question_id');
    }
}
