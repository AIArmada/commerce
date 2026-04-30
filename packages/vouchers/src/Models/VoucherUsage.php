<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $voucher_id
 * @property string $currency
 * @property int $discount_amount
 * @property string $channel
 * @property array<string, mixed>|null $target_definition
 * @property string|null $redeemed_by_type
 * @property string|null $redeemed_by_id
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property array<string, mixed>|null $cart_snapshot
 * @property Carbon $used_at
 * @property-read Voucher $voucher
 * @property-read Model|null $redeemedBy
 * @property-read string $user_identifier
 * @property-read string $cart_identifier
 */
final class VoucherUsage extends Model
{
    use HasUuids;

    public const CHANNEL_AUTOMATIC = 'automatic';

    public const CHANNEL_MANUAL = 'manual';

    public const CHANNEL_API = 'api';

    public $timestamps = false;

    protected $fillable = [
        'voucher_id',
        'discount_amount',
        'currency',
        'channel',
        'notes',
        'metadata',
        'redeemed_by_type',
        'redeemed_by_id',
        'used_at',
        'target_definition',
    ];

    public function getTable(): string
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');

        return $tables['voucher_usage'] ?? $prefix . 'voucher_usage';
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function redeemedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function isManual(): bool
    {
        return $this->getAttribute('channel') === self::CHANNEL_MANUAL;
    }

    public function isOrderRedemption(): bool
    {
        $redeemedBy = $this->resolveRedeemedBySafely();

        $redeemedByType = mb_strtolower((string) ($this->redeemed_by_type ?? ''));

        if ($redeemedByType !== '' && Str::contains($redeemedByType, 'order')) {
            return true;
        }

        if (! $redeemedBy) {
            return false;
        }

        return Str::contains(mb_strtolower($redeemedBy::class), 'order');
    }

    protected function userIdentifier(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $redeemedBy = $this->resolveRedeemedBySafely();

                if (! $redeemedBy) {
                    return 'N/A';
                }

                // Prefer email when available, regardless of morph alias/class naming.
                if (method_exists($redeemedBy, 'getAttribute')) {
                    /** @var string|null $email */
                    $email = $redeemedBy->getAttribute('email');

                    if ($email !== null && $email !== '') {
                        return $email;
                    }
                }

                if ($this->isOrderRedemption() && method_exists($redeemedBy, 'getAttribute')) {
                    /** @var string|null $orderNumber */
                    $orderNumber = $redeemedBy->getAttribute('order_number');

                    if ($orderNumber !== null && $orderNumber !== '') {
                        return $orderNumber;
                    }
                }

                // For other types, try to get an identifier
                if (method_exists($redeemedBy, 'getAttribute')) {
                    /** @var string|int|null $id */
                    $id = $redeemedBy->getAttribute('id');

                    return $id !== null ? (string) $id : 'N/A';
                }

                return 'N/A';
            }
        );
    }

    private function resolveRedeemedBySafely(): ?Model
    {
        if (! $this->relationLoaded('redeemedBy')) {
            return null;
        }

        $relation = $this->getRelation('redeemedBy');

        return $relation instanceof Model ? $relation : null;
    }

    protected function casts(): array
    {
        return [
            'discount_amount' => 'integer', // Stored as cents
            'metadata' => 'array',
            'target_definition' => 'array',
            'used_at' => 'datetime',
        ];
    }
}
