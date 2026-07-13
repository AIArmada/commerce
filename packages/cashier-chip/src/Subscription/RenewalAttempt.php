<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Subscription;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $subscription_id
 * @property string $status
 * @property int $amount_minor
 * @property string|null $period_key
 * @property string|null $purchase_id
 * @property string|null $last_error_code
 * @property Carbon|null $lease_expires_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Subscription $subscription
 */
class RenewalAttempt extends Model
{
    use HasUuids;

    protected $fillable = [
        'subscription_id',
        'status',
        'amount_minor',
        'period_key',
        'purchase_id',
        'last_error_code',
        'lease_expires_at',
        'completed_at',
    ];

    public function getTable(): string
    {
        return config('cashier-chip.database.tables.renewal_attempts', 'chip_renewal_attempts');
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'lease_expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }
}
