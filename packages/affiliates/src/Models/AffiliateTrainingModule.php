<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliateTrainingProgress> $progress
 */
final class AffiliateTrainingModule extends Model
{
    use HasUuids;

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

    public function progress(): HasMany
    {
        return $this->hasMany(AffiliateTrainingProgress::class, 'module_id');
    }
}
