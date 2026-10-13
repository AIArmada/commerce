<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardFundingEntryFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One bounded allocation of a confirmed funding source to one program +
 * currency. The source row carries the global total; entries draw from
 * it without ever overallocating. Immutable after creation: funding
 * evidence is operator-supplied setup input, never edited in place. A
 * correction is a new entry, never a rewrite.
 *
 * @property array<string, mixed>|null $evidence
 * @property CarbonImmutable|null $funded_at
 * @property string $id
 * @property string $program_id
 * @property string $currency
 * @property int $amount_minor
 * @property string $source_ref
 * @property string $source_id
 * @property array|null $evidence
 * @property string $funded_by_type
 * @property string|null $funded_by_id
 * @property string $funded_by_role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RewardFundingEntry extends Model
{
    /** @use HasFactory<RewardFundingEntryFactory> */
    use HasFactory;

    use HasUuids;

    public function getTable(): string
    {
        return config('rewards.database.tables.funding_entries', 'reward_funding_entries');
    }

    protected $fillable = [
        'program_id',
        'currency',
        'amount_minor',
        'source_ref',
        'source_id',
        'evidence',
        'funded_by_type',
        'funded_by_id',
        'funded_by_role',
        'funded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'evidence' => 'array',
            'funded_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Reward funding entries are immutable; record a correcting entry instead.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Reward funding entries cannot be deleted.');
        });
    }

    protected static function newFactory(): RewardFundingEntryFactory
    {
        return RewardFundingEntryFactory::new();
    }
}
