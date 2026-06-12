<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Models;

use AIArmada\Engagement\Database\Factories\EngagementCounterFactory;
use AIArmada\Engagement\Models\Concerns\UsesEngagementUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $counter_type
 * @property string $counter_key
 * @property int $count_value
 * @property CarbonImmutable|null $recalculated_at
 * @property array|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class EngagementCounter extends Model
{
    use HasFactory;
    use UsesEngagementUuid;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'counter_type',
        'counter_key',
        'count_value',
        'recalculated_at',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('engagement.database.tables.engagement_counters', 'engagement_counters');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'count_value' => 'integer',
            'metadata' => 'array',
            'recalculated_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): EngagementCounterFactory
    {
        return EngagementCounterFactory::new();
    }
}
