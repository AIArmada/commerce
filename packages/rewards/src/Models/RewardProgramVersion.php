<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardProgramVersionFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable published rules/terms snapshot. Rows are never updated or
 * deleted after publication; supersession appends a new version plus a
 * publication row. Effective windows derive from the next version's
 * effective_from — there is no mutable effective_to.
 *
 * @property array<string, mixed> $rules
 * @property array<string, mixed> $terms
 * @property list<string> $compatible_with
 * @property string $id
 * @property string $program_id
 * @property int $version
 * @property CarbonImmutable $effective_from
 * @property string $material_terms_hash
 * @property array $rules
 * @property array $terms
 * @property string|null $published_by_type
 * @property string|null $published_by_id
 * @property CarbonImmutable $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RewardProgram $program
 * @property-read RewardProgramPublication $publication
 */
class RewardProgramVersion extends Model
{
    /** @use HasFactory<RewardProgramVersionFactory> */
    use HasFactory;

    use HasUuids;

    public function getTable(): string
    {
        return config('rewards.database.tables.program_versions', 'reward_program_versions');
    }

    protected $fillable = [
        'program_id',
        'version',
        'effective_from',
        'material_terms_hash',
        'rules',
        'terms',
        'compatible_with',
        'published_by_type',
        'published_by_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'effective_from' => 'immutable_datetime',
            'rules' => 'array',
            'terms' => 'array',
            'compatible_with' => 'array',
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
     * @return HasOne<RewardProgramPublication, $this>
     */
    public function publication(): HasOne
    {
        return $this->hasOne(RewardProgramPublication::class, 'version_id');
    }

    /**
     * @return list<string>
     */
    public function componentTypes(): array
    {
        $components = $this->rules['components'] ?? [];

        if (! is_array($components)) {
            return [];
        }

        $types = [];

        foreach ($components as $component) {
            if (is_array($component) && is_string($component['type'] ?? null)) {
                $types[] = $component['type'];
            }
        }

        return $types;
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Reward program versions are immutable after publication.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Reward program versions cannot be deleted.');
        });
    }

    protected static function newFactory(): RewardProgramVersionFactory
    {
        return RewardProgramVersionFactory::new();
    }
}
