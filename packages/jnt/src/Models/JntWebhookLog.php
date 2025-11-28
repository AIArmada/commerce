<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $order_id
 * @property string|null $tracking_number
 * @property string|null $order_reference
 * @property string|null $digest
 * @property array<string, mixed>|null $headers
 * @property array<string, mixed>|null $payload
 * @property string $processing_status
 * @property string|null $processing_error
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read JntOrder|null $order
 */
class JntWebhookLog extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'tracking_number',
        'order_reference',
        'digest',
        'headers',
        'payload',
        'processing_status',
        'processing_error',
        'processed_at',
    ];

    public function getTable(): string
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        return $tables['webhook_logs'] ?? $prefix.'webhook_logs';
    }

    /**
     * Get the order that this webhook log belongs to.
     *
     * @return BelongsTo<JntOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(JntOrder::class, 'order_id');
    }

    /**
     * Check if the webhook has been processed.
     */
    public function isProcessed(): bool
    {
        return $this->processing_status === self::STATUS_PROCESSED;
    }

    /**
     * Check if the webhook processing failed.
     */
    public function isFailed(): bool
    {
        return $this->processing_status === self::STATUS_FAILED;
    }

    /**
     * Check if the webhook is pending processing.
     */
    public function isPending(): bool
    {
        return $this->processing_status === self::STATUS_PENDING;
    }

    /**
     * Mark the webhook as processed.
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'processing_status' => self::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark the webhook as failed.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'processing_status' => self::STATUS_FAILED,
            'processing_error' => $error,
            'processed_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
