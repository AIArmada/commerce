<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models;

use AIArmada\Cart\Models\Concerns\HasCartOwner;
use AIArmada\Cart\States\Clicked;
use AIArmada\Cart\States\Converted;
use AIArmada\Cart\States\Failed;
use AIArmada\Cart\States\Opened;
use AIArmada\Cart\States\RecoveryAttemptStatus;
use AIArmada\Cart\States\Sent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string $cart_id
 * @property string|null $template_id
 * @property string|null $recipient_email
 * @property string|null $recipient_phone
 * @property string|null $recipient_name
 * @property string $channel
 * @property RecoveryAttemptStatus $status
 * @property int $attempt_number
 * @property bool $is_control
 * @property bool $is_variant
 * @property string|null $discount_code
 * @property int|null $discount_value_cents
 * @property bool $free_shipping_offered
 * @property \Illuminate\Support\Carbon|null $offer_expires_at
 * @property int $cart_value_cents
 * @property int $cart_items_count
 * @property \Illuminate\Support\Carbon|null $scheduled_for
 * @property \Illuminate\Support\Carbon|null $queued_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $opened_at
 * @property \Illuminate\Support\Carbon|null $clicked_at
 * @property \Illuminate\Support\Carbon|null $converted_at
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property string|null $message_id
 * @property array<string, mixed>|null $metadata
 * @property string|null $failure_reason
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read RecoveryCampaign $campaign
 * @property-read RecoveryTemplate|null $template
 * @property-read Model $cart
 */
class RecoveryAttempt extends Model
{
    use HasCartOwner;
    use HasStates;
    use HasUuids;

    protected $fillable = [
        'campaign_id',
        'cart_id',
        'template_id',
        'recipient_email',
        'recipient_phone',
        'recipient_name',
        'channel',
        'status',
        'attempt_number',
        'is_control',
        'is_variant',
        'discount_code',
        'discount_value_cents',
        'free_shipping_offered',
        'offer_expires_at',
        'cart_value_cents',
        'cart_items_count',
        'scheduled_for',
        'queued_at',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'converted_at',
        'failed_at',
        'message_id',
        'metadata',
        'failure_reason',
    ];

    public function getTable(): string
    {
        $prefix = config('cart.database.table_prefix', 'cart_');

        return $prefix . 'recovery_attempts';
    }

    /**
     * @return BelongsTo<RecoveryCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(RecoveryCampaign::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<RecoveryTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(RecoveryTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function cart(): BelongsTo
    {
        $cartModel = config('cart.models.cart', CartModel::class);

        return $this->belongsTo($cartModel, 'cart_id');
    }

    public function isScheduled(): bool
    {
        return $this->status->isScheduled();
    }

    public function isSent(): bool
    {
        return $this->status->isSent();
    }

    public function isOpened(): bool
    {
        return $this->status->isOpened();
    }

    public function isClicked(): bool
    {
        return $this->status->isClicked();
    }

    public function isConverted(): bool
    {
        return $this->status->isConverted();
    }

    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    protected static function booted(): void
    {
        static::saving(function (RecoveryAttempt $attempt): void {
            if (! self::ownerScopingEnabled()) {
                return;
            }

            $owner = self::resolveCurrentOwner();

            if ($owner === null) {
                throw new RuntimeException('Owner scoping is enabled but no owner was resolved while saving a recovery attempt.');
            }

            if ($attempt->campaign_id !== '' && $attempt->campaign_id !== null) {
                $exists = RecoveryCampaign::query()
                    ->forOwner($owner, includeGlobal: false)
                    ->whereKey($attempt->campaign_id)
                    ->exists();

                if (! $exists) {
                    throw new RuntimeException('Invalid campaign_id: does not belong to the current owner scope.');
                }
            }

            if ($attempt->template_id !== '' && $attempt->template_id !== null) {
                $exists = RecoveryTemplate::query()
                    ->forOwner($owner, includeGlobal: true)
                    ->whereKey($attempt->template_id)
                    ->exists();

                if (! $exists) {
                    throw new RuntimeException('Invalid template_id: does not belong to the current owner scope.');
                }
            }
        });
    }

    public function markAsSent(?string $messageId = null): void
    {
        $this->update([
            'status' => Sent::class,
            'sent_at' => now(),
            'message_id' => $messageId,
        ]);
    }

    public function markAsOpened(): void
    {
        if ($this->opened_at === null) {
            $this->update([
                'status' => Opened::class,
                'opened_at' => now(),
            ]);
        }
    }

    public function markAsClicked(): void
    {
        if ($this->clicked_at === null) {
            $this->markAsOpened();
            $this->update([
                'status' => Clicked::class,
                'clicked_at' => now(),
            ]);
        }
    }

    public function markAsConverted(): void
    {
        $this->markAsClicked();
        $this->update([
            'status' => Converted::class,
            'converted_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => Failed::class,
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }

    protected function casts(): array
    {
        return [
            'status' => RecoveryAttemptStatus::class,
            'is_control' => 'boolean',
            'is_variant' => 'boolean',
            'free_shipping_offered' => 'boolean',
            'offer_expires_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'converted_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
