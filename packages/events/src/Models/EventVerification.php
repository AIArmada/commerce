<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventVerificationFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $verifiable_type
 * @property string $verifiable_id
 * @property string|null $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string $verification_type
 * @property string $status
 * @property string|null $confidence_level
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string|null $source_label
 * @property string|null $source_url
 * @property string|null $verified_by_type
 * @property string|null $verified_by_id
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $rejection_reason
 * @property string|null $notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $verifiable
 */
final class EventVerification extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'verifiable_type', 'verifiable_id',
        'event_id', 'event_occurrence_id', 'event_session_id',
        'verification_type', 'status', 'confidence_level',
        'source_type', 'source_id', 'source_label', 'source_url',
        'verified_by_type', 'verified_by_id',
        'verified_at', 'rejected_at', 'expired_at', 'revoked_at',
        'rejection_reason', 'notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_verifications', 'event_verifications');
    }

    protected function casts(): array
    {
        return [
            'verified_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function verifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return BelongsTo<EventOccurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<EventSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    protected static function newFactory(): EventVerificationFactory
    {
        return EventVerificationFactory::new();
    }
}
