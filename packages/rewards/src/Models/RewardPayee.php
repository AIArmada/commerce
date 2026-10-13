<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardPayeeFactory;
use AIArmada\Rewards\Enums\RewardPayeeStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Per-recipient payout destination. The stored account_ref is an operator
 * handle (masked display in account_display); full credentials live with
 * the operator/provider, never here. Verification is an independent staff
 * act: verifier must differ from the recipient. A payee change supersedes
 * the old row; frozen per-operation snapshots keep reconciling against the
 * original terms.
 *
 * @property RewardPayeeStatus $status
 * @property array<string, mixed>|null $evidence
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $superseded_at
 * @property string $id
 * @property string $recipient_type
 * @property string $recipient_id
 * @property string $method
 * @property string $account_ref
 * @property string $account_display
 * @property array|null $evidence
 * @property string|null $verified_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RewardPayee extends Model
{
    /** @use HasFactory<RewardPayeeFactory> */
    use HasFactory;

    use HasUuids;

    public function getTable(): string
    {
        return config('rewards.database.tables.payees', 'reward_payees');
    }

    protected $fillable = [
        'recipient_type',
        'recipient_id',
        'method',
        'account_ref',
        'account_display',
        'status',
        'evidence',
        'verified_by',
        'verified_at',
        'rejected_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RewardPayeeStatus::class,
            'evidence' => 'array',
            'verified_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (RewardPayee $payee): void {
            foreach (['recipient_type', 'recipient_id', 'method', 'account_ref', 'account_display'] as $field) {
                if ($payee->isDirty($field)) {
                    throw new LogicException("Reward payee [{$field}] is frozen; register a new payee instead.");
                }
            }

            foreach (['verified_at', 'rejected_at', 'superseded_at'] as $field) {
                if ($payee->getOriginal($field) !== null && $payee->isDirty($field)) {
                    throw new LogicException("Reward payee [{$field}] is an immutable transition timestamp.");
                }
            }

            if ($payee->isDirty('status')) {
                $original = $payee->getOriginal('status');
                $from = $original instanceof RewardPayeeStatus
                    ? $original
                    : RewardPayeeStatus::tryFrom((string) $original);
                $allowed = $from === RewardPayeeStatus::Pending
                    ? [RewardPayeeStatus::Verified, RewardPayeeStatus::Rejected, RewardPayeeStatus::Superseded]
                    : ($from === RewardPayeeStatus::Verified ? [RewardPayeeStatus::Superseded] : []);

                if (! in_array($payee->status, $allowed, true)) {
                    throw new LogicException('Invalid reward payee status transition.');
                }
            }
        });

        static::deleting(static function (): void {
            throw new LogicException('Reward payees cannot be deleted; reject or supersede them instead.');
        });
    }

    protected static function newFactory(): RewardPayeeFactory
    {
        return RewardPayeeFactory::new();
    }
}
