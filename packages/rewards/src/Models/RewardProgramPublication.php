<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardProgramPublicationFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only version publication evidence. One row per published version,
 * recording supersession order. Never updated or deleted.
 *
 * @property string $id
 * @property string $program_id
 * @property string $version_id
 * @property string|null $previous_version_id
 * @property int $version
 * @property CarbonImmutable $effective_from
 * @property string|null $reason
 * @property string|null $published_by_type
 * @property string|null $published_by_id
 * @property CarbonImmutable $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RewardProgram $program
 * @property-read RewardProgramVersion $version
 */
class RewardProgramPublication extends Model
{
    /** @use HasFactory<RewardProgramPublicationFactory> */
    use HasFactory;

    use HasUuids;

    public function getTable(): string
    {
        return config('rewards.database.tables.version_publications', 'reward_version_publications');
    }

    protected $fillable = [
        'program_id',
        'version_id',
        'previous_version_id',
        'version',
        'effective_from',
        'reason',
        'published_by_type',
        'published_by_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'effective_from' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<RewardProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(RewardProgram::class, 'program_id');
    }

    /**
     * @return BelongsTo<RewardProgramVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(RewardProgramVersion::class, 'version_id');
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Reward version publications are append-only.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Reward version publications cannot be deleted.');
        });
    }

    protected static function newFactory(): RewardProgramPublicationFactory
    {
        return RewardProgramPublicationFactory::new();
    }
}
