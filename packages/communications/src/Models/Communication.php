<?php

declare(strict_types=1);

namespace AIArmada\Communications\Models;

use AIArmada\CommerceSupport\Persistence\DeletesAtomically;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Features\Aggregate\Api\CommunicationDependentsCleaner;
use AIArmada\Communications\Runtime\AggregateRelationModels;
use AIArmada\Communications\Runtime\DeliveryRelationModels;
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
 * @property-read RelatedCommunicationBatch|null $batch
 * @property-read RelatedCommunicationThread|null $thread
 * @property-read Communication|null $parent
 * @property-read Collection<int, Communication> $children
 * @property-read Model|Eloquent $subject
 * @property-read Model|Eloquent $sender
 * @property-read Collection<int, RelatedCommunicationRecipient> $recipients
 * @property-read Collection<int, RelatedCommunicationContent> $contents
 * @property-read Collection<int, RelatedCommunicationDelivery> $deliveries
 * @property-read Collection<int, RelatedCommunicationEvent> $events
 * @property-read Collection<int, RelatedCommunicationReference> $references
 * @property-read Collection<int, RelatedCommunicationAttachment> $attachments
 * @property-read Model|Eloquent $owner
 *
 * @phpstan-type RelatedCommunicationAttachment \AIArmada\Communications\Features\Aggregate\Models\CommunicationAttachment
 * @phpstan-type RelatedCommunicationBatch \AIArmada\Communications\Features\Aggregate\Models\CommunicationBatch
 * @phpstan-type RelatedCommunicationContent \AIArmada\Communications\Features\Aggregate\Models\CommunicationContent
 * @phpstan-type RelatedCommunicationRecipient \AIArmada\Communications\Features\Aggregate\Models\CommunicationRecipient
 * @phpstan-type RelatedCommunicationReference \AIArmada\Communications\Features\Aggregate\Models\CommunicationReference
 * @phpstan-type RelatedCommunicationThread \AIArmada\Communications\Features\Aggregate\Models\CommunicationThread
 * @phpstan-type RelatedCommunicationDelivery \AIArmada\Communications\Features\Delivery\Models\CommunicationDelivery
 * @phpstan-type RelatedCommunicationEvent \AIArmada\Communications\Features\Delivery\Models\CommunicationEvent
 */
final class Communication extends Model
{
    use DeletesAtomically;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $fillable = [
        'batch_id',
        'thread_id',
        'parent_id',
        'subject_id',
        'sender_id',
        'direction',
        'category',
        'priority',
        'purpose',
        'idempotency_key',
        'locale',
        'timezone',
        'scheduled_at',
        'queued_at',
        'processing_at',
        'completed_at',
        'cancelled_at',
        'failed_at',
        'expires_at',
        'metadata',
    ];

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
     * @return BelongsTo<RelatedCommunicationBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(app(AggregateRelationModels::class)->batch(), 'batch_id');
    }

    /**
     * @return BelongsTo<RelatedCommunicationThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(app(AggregateRelationModels::class)->thread(), 'thread_id');
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
     * @return HasMany<RelatedCommunicationRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(app(AggregateRelationModels::class)->recipient(), 'communication_id');
    }

    /**
     * @return HasMany<RelatedCommunicationContent, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(app(AggregateRelationModels::class)->content(), 'communication_id');
    }

    /**
     * @return HasMany<RelatedCommunicationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(app(DeliveryRelationModels::class)->delivery(), 'communication_id');
    }

    /**
     * @return HasMany<RelatedCommunicationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(app(DeliveryRelationModels::class)->event(), 'communication_id');
    }

    /**
     * @return HasMany<RelatedCommunicationReference, $this>
     */
    public function references(): HasMany
    {
        return $this->hasMany(app(AggregateRelationModels::class)->reference(), 'communication_id');
    }

    /**
     * @return HasMany<RelatedCommunicationAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(app(AggregateRelationModels::class)->attachment(), 'communication_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (Communication $communication): void {
            app(CommunicationDependentsCleaner::class)->cleanup((string) $communication->getKey());
        });
    }
}
