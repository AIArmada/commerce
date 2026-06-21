<?php

declare(strict_types=1);

namespace AIArmada\Communications\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $batch_id
 * @property string|null $thread_id
 * @property string|null $parent_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $sender_type
 * @property string|null $sender_id
 * @property CommunicationDirection $direction
 * @property CommunicationCategory $category
 * @property CommunicationPriority $priority
 * @property string $purpose
 * @property CommunicationStatus $status
 * @property string|null $idempotency_key
 * @property string|null $locale
 * @property string|null $timezone
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $queued_at
 * @property CarbonImmutable|null $processing_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $expires_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CommunicationBatch|null $batch
 * @property-read CommunicationThread|null $thread
 * @property-read Communication|null $parent
 * @property-read Collection<int, Communication> $children
 * @property-read Model|Eloquent $subject
 * @property-read Model|Eloquent $sender
 * @property-read Collection<int, CommunicationRecipient> $recipients
 * @property-read Collection<int, CommunicationContent> $contents
 * @property-read Collection<int, CommunicationDelivery> $deliveries
 * @property-read Collection<int, CommunicationEvent> $events
 * @property-read Collection<int, CommunicationReference> $references
 * @property-read Collection<int, CommunicationAttachment> $attachments
 * @property-read Model|Eloquent $owner
 */
final class Communication extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $guarded = [];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.communications', 'communications');
    }

    protected function casts(): array
    {
        return [
            'direction' => CommunicationDirection::class,
            'category' => CommunicationCategory::class,
            'priority' => CommunicationPriority::class,
            'status' => CommunicationStatus::class,
            'scheduled_at' => 'immutable_datetime',
            'queued_at' => 'immutable_datetime',
            'processing_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CommunicationBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CommunicationBatch::class, 'batch_id');
    }

    /**
     * @return BelongsTo<CommunicationThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(CommunicationThread::class, 'thread_id');
    }

    /**
     * @return BelongsTo<Communication, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Communication::class, 'parent_id');
    }

    /**
     * @return HasMany<Communication, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Communication::class, 'parent_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<CommunicationRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationRecipient::class, 'communication_id');
    }

    /**
     * @return HasMany<CommunicationContent, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(CommunicationContent::class, 'communication_id');
    }

    /**
     * @return HasMany<CommunicationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(CommunicationDelivery::class, 'communication_id');
    }

    /**
     * @return HasMany<CommunicationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(CommunicationEvent::class, 'communication_id');
    }

    /**
     * @return HasMany<CommunicationReference, $this>
     */
    public function references(): HasMany
    {
        return $this->hasMany(CommunicationReference::class, 'communication_id');
    }

    /**
     * @return HasMany<CommunicationAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(CommunicationAttachment::class, 'communication_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (Communication $communication): void {
            $communication->recipients()->each(fn (CommunicationRecipient $r) => $r->delete());
            $communication->contents()->each(fn (CommunicationContent $c) => $c->delete());
            $communication->deliveries()->each(fn (CommunicationDelivery $d) => $d->delete());
            $communication->events()->each(fn (CommunicationEvent $e) => $e->delete());
            $communication->references()->each(fn (CommunicationReference $r) => $r->delete());
            $communication->attachments()->each(fn (CommunicationAttachment $a) => $a->delete());
        });
    }
}
