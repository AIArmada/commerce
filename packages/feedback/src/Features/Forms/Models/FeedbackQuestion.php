<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Feedback\Features\Forms\Actions\DeleteFeedbackQuestionDependentsAction;
use AIArmada\Feedback\Features\Forms\Api\QuestionDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
 */
final class FeedbackQuestion extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'feedback.owner';

    protected $fillable = [
        'feedback_form_id', 'feedback_section_id',
        'key', 'type', 'label', 'description', 'help_text', 'placeholder',
        'is_required', 'is_scored', 'order_column',
        'validation_rules', 'visibility_rules', 'scoring_rules',
        'settings', 'metadata',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $question): void {
            app(DeleteFeedbackQuestionDependentsAction::class)->execute($question);
        });
    }

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

    public function definition(): QuestionDefinition
    {
        $this->loadMissing('options');

        return new QuestionDefinition(
            id: $this->id,
            key: $this->key,
            type: $this->type,
            isRequired: $this->is_required,
            isScored: $this->is_scored,
            settings: $this->settings ?? [],
            visibilityRules: $this->visibility_rules ?? [],
            scoringRules: $this->scoring_rules ?? [],
            options: $this->options->map(static fn (FeedbackQuestionOption $option): array => [
                'value' => $option->value,
                'score' => $option->score !== null ? (float) $option->score : null,
            ])->values()->all(),
        );
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
}
