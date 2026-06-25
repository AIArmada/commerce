<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Feedback\Enums\FeedbackTemplateStatus;
use AIArmada\Feedback\Models\Concerns\UsesFeedbackUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string $purpose
 * @property string|null $category
 * @property FeedbackTemplateStatus $status
 * @property array|null $definition
 * @property array|null $settings
 * @property array|null $metadata
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class FeedbackTemplate extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesFeedbackUuid;

    protected static string $ownerScopeConfigKey = 'feedback.owner';

    protected $fillable = [
        'name', 'slug', 'purpose', 'category', 'status',
        'definition', 'settings', 'metadata',
        'published_at', 'archived_at',
    ];

    public function getTable(): string
    {
        $prefix = (string) config('feedback.database.table_prefix', '');

        return $prefix . (string) config('feedback.database.tables.templates', 'feedback_templates');
    }

    protected function casts(): array
    {
        return [
            'status' => FeedbackTemplateStatus::class,
            'definition' => 'array',
            'settings' => 'array',
            'metadata' => 'array',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
