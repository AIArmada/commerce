<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardSourceCoordinatorFactory;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Global per-source evaluation serializer.
 *
 * Unique (source, component, role): every evaluation locks its coordinator
 * row first, so cross-program claim checks and inserts for one source can
 * never interleave. Concurrent coordinator creation surfaces as a unique
 * violation that the caller retries outside its transaction. Rows are
 * coordination evidence — never updated or deleted.
 *
 * @property RewardComponent $component
 * @property RewardBeneficiaryRole $beneficiary_role
 * @property string $id
 * @property string $source_business_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RewardSourceCoordinator extends Model
{
    /** @use HasFactory<RewardSourceCoordinatorFactory> */
    use HasFactory;

    use HasUuids;

    public function getTable(): string
    {
        return config('rewards.database.tables.source_coordinators', 'reward_source_coordinators');
    }

    protected $fillable = [
        'source_business_key',
        'component',
        'beneficiary_role',
    ];

    protected function casts(): array
    {
        return [
            'component' => RewardComponent::class,
            'beneficiary_role' => RewardBeneficiaryRole::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Reward source coordinators are immutable.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Reward source coordinators cannot be deleted.');
        });
    }

    protected static function newFactory(): RewardSourceCoordinatorFactory
    {
        return RewardSourceCoordinatorFactory::new();
    }
}
