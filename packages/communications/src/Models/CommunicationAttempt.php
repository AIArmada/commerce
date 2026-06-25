<?php

declare(strict_types=1);

namespace AIArmada\Communications\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
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
 * @property string $delivery_id
 * @property int $attempt_number
 * @property string $provider
 * @property string|null $provider_message_id
 * @property array|null $request_payload
 * @property array|null $response_payload
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $responded_at
 * @property CarbonImmutable|null $failed_at
 * @property int|null $duration_ms
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CommunicationDelivery $delivery
 * @property-read Model|Eloquent $owner
 */
final class CommunicationAttempt extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $fillable = [
        'delivery_id',
        'attempt_number',
        'provider',
        'provider_message_id',
        'request_payload',
        'response_payload',
        'started_at',
        'accepted_at',
        'responded_at',
        'failed_at',
        'duration_ms',
        'failure_code',
        'failure_message',
        'metadata',
    ];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.attempts', 'communication_attempts');
    }

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'started_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'duration_ms' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CommunicationDelivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(CommunicationDelivery::class, 'delivery_id');
    }
}
