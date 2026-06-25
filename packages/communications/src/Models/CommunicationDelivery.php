<?php

declare(strict_types=1);

namespace AIArmada\Communications\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Communications\Enums\DeliveryStatus;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $communication_id
 * @property string|null $recipient_id
 * @property string|null $content_id
 * @property string $channel
 * @property string $provider
 * @property string|null $provider_account_key
 * @property string|null $provider_message_id
 * @property DeliveryStatus $status
 * @property string|null $destination_ciphertext
 * @property string|null $destination_hash
 * @property string|null $destination_hint
 * @property int $attempt_count
 * @property int $max_attempts
 * @property int|null $cost_minor
 * @property string|null $cost_currency
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $queued_at
 * @property CarbonImmutable|null $sending_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $clicked_at
 * @property CarbonImmutable|null $replied_at
 * @property CarbonImmutable|null $bounced_at
 * @property CarbonImmutable|null $complained_at
 * @property CarbonImmutable|null $unsubscribed_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $suppressed_at
 * @property CarbonImmutable|null $last_attempt_at
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Communication $communication
 * @property-read CommunicationRecipient|null $recipient
 * @property-read CommunicationContent|null $content
 * @property-read Collection<int, CommunicationAttempt> $attempts
 * @property-read Collection<int, CommunicationEvent> $events
 * @property-read Collection<int, CommunicationTrackingToken> $trackingTokens
 * @property-read Model|Eloquent $owner
 */
final class CommunicationDelivery extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $fillable = [
        'communication_id',
        'recipient_id',
        'content_id',
        'channel',
        'provider',
        'provider_account_key',
        'provider_message_id',
        'status',
        'destination_ciphertext',
        'destination_hash',
        'destination_hint',
        'attempt_count',
        'max_attempts',
        'cost_minor',
        'cost_currency',
        'scheduled_at',
        'queued_at',
        'sending_at',
        'accepted_at',
        'sent_at',
        'received_at',
        'delivered_at',
        'opened_at',
        'read_at',
        'clicked_at',
        'replied_at',
        'bounced_at',
        'complained_at',
        'unsubscribed_at',
        'failed_at',
        'cancelled_at',
        'expired_at',
        'suppressed_at',
        'last_attempt_at',
        'failure_code',
        'failure_message',
        'metadata',
    ];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.deliveries', 'communication_deliveries');
    }

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
            'cost_minor' => 'integer',
            'scheduled_at' => 'immutable_datetime',
            'queued_at' => 'immutable_datetime',
            'sending_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
            'clicked_at' => 'immutable_datetime',
            'bounced_at' => 'immutable_datetime',
            'complained_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'suppressed_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Communication, $this>
     */
    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class, 'communication_id');
    }

    /**
     * @return BelongsTo<CommunicationRecipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(CommunicationRecipient::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<CommunicationContent, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(CommunicationContent::class, 'content_id');
    }

    /**
     * @return HasMany<CommunicationAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(CommunicationAttempt::class, 'delivery_id');
    }

    /**
     * @return HasMany<CommunicationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(CommunicationEvent::class, 'delivery_id');
    }

    /**
     * @return HasMany<CommunicationTrackingToken, $this>
     */
    public function trackingTokens(): HasMany
    {
        return $this->hasMany(CommunicationTrackingToken::class, 'delivery_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (CommunicationDelivery $delivery): void {
            $delivery->attempts()->each(fn (CommunicationAttempt $a) => $a->delete());
            $delivery->events()->each(fn (CommunicationEvent $e) => $e->delete());
            $delivery->trackingTokens()->each(fn (CommunicationTrackingToken $t) => $t->delete());
        });
    }
}
