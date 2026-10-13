<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardCapCounterFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Durable cap counter: unique (cap_key, window_key). consumed is the only
 * mutable field, incremented under the row lock after a limit recheck, so
 * concurrent evaluations can never overspend a cap. Cross-program counters
 * use the literal '*' family marker.
 *
 * @property string $id
 * @property string $cap_key
 * @property string $window_key
 * @property string $family_key
 * @property string $scope
 * @property int $limit_value
 * @property int $consumed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RewardCapCounter extends Model
{
    /** @use HasFactory<RewardCapCounterFactory> */
    use HasFactory;

    use HasUuids;

    public function getTable(): string
    {
        return config('rewards.database.tables.cap_counters', 'reward_cap_counters');
    }

    protected $fillable = [
        'cap_key',
        'window_key',
        'family_key',
        'scope',
        'limit_value',
        'consumed',
    ];

    protected function casts(): array
    {
        return [
            'limit_value' => 'integer',
            'consumed' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (RewardCapCounter $counter): void {
            foreach ($counter->getDirty() as $field => $value) {
                if (! in_array($field, ['consumed', 'updated_at'], true)) {
                    throw new LogicException("Reward cap counter [{$field}] is immutable.");
                }
            }

            if ($counter->consumed < (int) $counter->getOriginal('consumed')) {
                throw new LogicException('Reward cap counter [consumed] is monotonic and cannot decrease.');
            }
        });

        static::deleting(static function (): void {
            throw new LogicException('Reward cap counters cannot be deleted.');
        });
    }

    protected static function newFactory(): RewardCapCounterFactory
    {
        return RewardCapCounterFactory::new();
    }
}
