<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Models;

use AIArmada\Rewards\Database\Factories\RewardPointReservationSubmissionFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * External-submission registry for point reservations.
 *
 * A reservation held inside the point ledger may be handed to external
 * fulfillment (benefit redemption, voucher issue, provider call). The ledger
 * cannot observe that handoff, so every host flow MUST mark the reservation
 * here BEFORE the external call (via MarkRewardReservationSubmittedAction).
 * Reversal attestation reads this registry under lock: only a reservation
 * with no row — or a confirmed-failed handoff — may be voided. Sending and
 * unknown handoffs always refuse reversal.
 *
 * @property string $id
 * @property string $reservation_key
 * @property string $status
 * @property string|null $reference
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RewardPointReservationSubmission extends Model
{
    /** @use HasFactory<RewardPointReservationSubmissionFactory> */
    use HasFactory;

    use HasUuids;

    public const string STATUS_SENDING = 'sending';

    public const string STATUS_SUBMITTED = 'submitted';

    public const string STATUS_UNKNOWN = 'unknown';

    public const string STATUS_DELIVERED = 'delivered';

    public const string STATUS_FAILED = 'failed';

    public function getTable(): string
    {
        return config('rewards.database.tables.point_reservation_submissions', 'reward_point_reservation_submissions');
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reservation_key',
        'status',
        'reference',
        'submitted_at',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_SENDING,
            self::STATUS_SUBMITTED,
            self::STATUS_UNKNOWN,
            self::STATUS_DELIVERED,
            self::STATUS_FAILED,
        ];
    }

    protected static function newFactory(): RewardPointReservationSubmissionFactory
    {
        return RewardPointReservationSubmissionFactory::new();
    }
}
