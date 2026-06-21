<?php

declare(strict_types=1);

namespace AIArmada\Communications\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Communications\Enums\CommunicationEventSource;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $communication_id
 * @property string|null $delivery_id
 * @property string|null $attempt_id
 * @property string $event
 * @property CommunicationEventSource $source
 * @property string $provider
 * @property string|null $provider_event_id
 * @property string|null $provider_message_id
 * @property CarbonImmutable|null $occurred_at
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $signature_validated_at
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $ignored_at
 * @property CarbonImmutable|null $failed_at
 * @property array|null $payload
 * @property string|null $failure_message
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Communication|null $communication
 * @property-read CommunicationDelivery|null $delivery
 * @property-read CommunicationAttempt|null $attempt
 * @property-read Model|Eloquent $owner
 */
final class CommunicationEvent extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $guarded = [];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.events', 'communication_events');
    }

    protected function casts(): array
    {
        return [
            'source' => CommunicationEventSource::class,
            'occurred_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'signature_validated_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'ignored_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'payload' => 'array',
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
     * @return BelongsTo<CommunicationDelivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(CommunicationDelivery::class, 'delivery_id');
    }

    /**
     * @return BelongsTo<CommunicationAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CommunicationAttempt::class, 'attempt_id');
    }
}
