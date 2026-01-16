<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $title
 * @property string|null $description
 * @property string|null $content
 * @property string $type
 * @property string|null $video_url
 * @property array|null $resources
 * @property array|null $quiz
 * @property int|null $passing_score
 * @property int $duration_minutes
 * @property int $sort_order
 * @property bool $is_required
 * @property bool $is_active
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AffiliateTrainingProgress> $progress
 */
class AffiliateTrainingModule extends Model
{
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    protected $fillable = [
        'title',
        'description',
        'content',
        'type',
        'video_url',
        'resources',
        'quiz',
        'passing_score',
        'duration_minutes',
        'sort_order',
        'is_required',
        'is_active',
        'owner_type',
        'owner_id',
    ];

    protected $casts = [
        'resources' => 'array',
        'quiz' => 'array',
        'passing_score' => 'integer',
        'duration_minutes' => 'integer',
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.training_modules', 'affiliate_training_modules');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, Model | string | null $owner = OwnerContext::CURRENT, bool $includeGlobal = false): Builder
    {
        if (! config('affiliates.owner.enabled', false)) {
            return $query;
        }

        $includeGlobal = $includeGlobal && (bool) config('affiliates.owner.include_global', false);

        return $this->baseScopeForOwner($query, $owner, $includeGlobal);
    }

    /**
     * @return HasMany<AffiliateTrainingProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(AffiliateTrainingProgress::class, 'module_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $module): void {
            if (! config('affiliates.owner.enabled', false)) {
                return;
            }

            if ($module->owner_id !== null) {
                return;
            }

            if (! config('affiliates.owner.auto_assign_on_create', true)) {
                return;
            }

            $owner = OwnerContext::resolve();

            if ($owner) {
                $module->owner_type = $owner->getMorphClass();
                $module->owner_id = $owner->getKey();
            }
        });

        static::deleting(function (self $module): void {
            $module->progress()->delete();
        });
    }
}
