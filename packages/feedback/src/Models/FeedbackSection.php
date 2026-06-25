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
 * @property string|null $key
 * @property string $title
 * @property string|null $description
 * @property int $order_column
 * @property array|null $settings
 * @property array|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read FeedbackForm $form
 * @property-read Collection<int, FeedbackQuestion> $questions
 */
final class FeedbackSection extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesFeedbackUuid;

    protected static string $ownerScopeConfigKey = 'feedback.owner';

    protected $fillable = [
        'feedback_form_id', 'key', 'title', 'description', 'order_column',
        'settings', 'metadata',
    ];

    public function getTable(): string
    {
        $prefix = (string) config('feedback.database.table_prefix', '');

        return $prefix . (string) config('feedback.database.tables.sections', 'feedback_sections');
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'metadata' => 'array',
            'order_column' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(FeedbackForm::class, 'feedback_form_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FeedbackQuestion::class, 'feedback_section_id');
    }
}
