<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardSourceComponentClaimFactory;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Global source/component coordination claim.
 *
 * Unique (source, component, role, family): the first claim wins unless an
 * explicit bilateral compatibility holds between the frozen snapshots. The
 * compatible_snapshot freezes the claiming version's compatible_with list at
 * claim time so later policy edits cannot rewrite stacking history.
 *
 * @property RewardComponent $component
 * @property RewardBeneficiaryRole $beneficiary_role
 * @property list<string> $compatible_snapshot
 * @property string $id
 * @property string $source_business_key
 * @property string $family_key
 * @property string $program_id
 * @property string $award_id
 * @property CarbonImmutable $claimed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RewardSourceComponentClaim extends Model
{
    /** @use HasFactory<RewardSourceComponentClaimFactory> */
    use HasFactory;

    use HasUuids;

    public function getTable(): string
    {
        return config('rewards.database.tables.source_component_claims', 'reward_source_component_claims');
    }

    protected $fillable = [
        'source_business_key',
        'component',
        'beneficiary_role',
        'family_key',
        'program_id',
        'award_id',
        'compatible_snapshot',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'component' => RewardComponent::class,
            'beneficiary_role' => RewardBeneficiaryRole::class,
            'compatible_snapshot' => 'array',
            'claimed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Reward source/component claims are immutable coordination evidence.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Reward source/component claims cannot be deleted.');
        });
    }

    protected static function newFactory(): RewardSourceComponentClaimFactory
    {
        return RewardSourceComponentClaimFactory::new();
    }
}
