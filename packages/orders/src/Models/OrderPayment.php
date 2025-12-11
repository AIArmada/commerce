<?php

declare(strict_types=1);

namespace AIArmada\Orders\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $order_id
 * @property string $gateway
 * @property string|null $transaction_id
 * @property int $amount
 * @property string $currency
 * @property string $status
 * @property string|null $failure_reason
 * @property array|null $metadata
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Order $order
 */
class OrderPayment extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'order_id',
        'gateway',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'failure_reason',
        'metadata',
        'paid_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'currency' => 'MYR',
    ];

    public function getTable(): string
    {
        return config('orders.database.tables.order_payments', 'order_payments');
    }

    // ─────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<Order, OrderPayment>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ─────────────────────────────────────────────────────────────
    // STATUS HELPERS
    // ─────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function markAsCompleted(?string $transactionId = null): self
    {
        $this->status = 'completed';
        $this->paid_at = now();

        if ($transactionId !== null) {
            $this->transaction_id = $transactionId;
        }

        $this->save();

        return $this;
    }

    public function markAsFailed(string $reason): self
    {
        $this->status = 'failed';
        $this->failure_reason = $reason;
        $this->save();

        return $this;
    }

    // ─────────────────────────────────────────────────────────────
    // MONEY HELPERS
    // ─────────────────────────────────────────────────────────────

    public function getFormattedAmount(): string
    {
        $symbol = match ($this->currency) {
            'MYR' => 'RM',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $this->currency . ' ',
        };

        return $symbol . number_format($this->amount / 100, 2);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }
}
