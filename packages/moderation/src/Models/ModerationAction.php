<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Moderation\Enums\ModerationActionType;
use AIArmada\Moderation\Models\Concerns\UsesModerationUuid;
use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $actionable_type
 * @property string|null $actionable_id
 * @property string|null $actioned_by_type
 * @property string|null $actioned_by_id
 * @property ModerationActionType $type
 * @property string $reason
 * @property string|null $notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $actionable
 * @property-read Model|Eloquent $actionedBy
 */
final class ModerationAction extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesModerationUuid;

    protected $fillable = [
        'actionable_type', 'actionable_id',
        'actioned_by_type', 'actioned_by_id',
        'type', 'reason', 'notes', 'metadata',
    ];

    protected static string $ownerScopeConfigKey = 'moderation.features.owner';

    public function getTable(): string
    {
        return config('moderation.database.tables.moderation_actions', 'moderation_actions');
    }

    protected function casts(): array
    {
        return [
            'type' => ModerationActionType::class,
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actionedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
