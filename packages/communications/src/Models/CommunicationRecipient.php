<?php

declare(strict_types=1);

namespace AIArmada\Communications\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Communications\Enums\RecipientRole;
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
 * @property string $communication_id
 * @property string|null $recipient_type
 * @property string|null $recipient_id
 * @property RecipientRole $role
 * @property string|null $external_key
 * @property string|null $display_name
 * @property string|null $locale
 * @property string|null $timezone
 * @property array|null $snapshot
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Communication $communication
 * @property-read Model|Eloquent $recipient
 * @property-read Collection<int, CommunicationDelivery> $deliveries
 * @property-read Collection<int, CommunicationContent> $contents
 * @property-read Model|Eloquent $owner
 */
final class CommunicationRecipient extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $fillable = [
        'communication_id',
        'recipient_type',
        'recipient_id',
        'role',
        'external_key',
        'display_name',
        'locale',
        'timezone',
        'snapshot',
        'metadata',
    ];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.recipients', 'communication_recipients');
    }

    protected function casts(): array
    {
        return [
            'role' => RecipientRole::class,
            'snapshot' => 'array',
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
     * @return MorphTo<Model, $this>
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<CommunicationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(CommunicationDelivery::class, 'recipient_id');
    }

    /**
     * @return HasMany<CommunicationContent, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(CommunicationContent::class, 'recipient_id');
    }
}
