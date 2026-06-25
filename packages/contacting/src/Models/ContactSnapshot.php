<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Contacting\Database\Factories\ContactSnapshotFactory;
use AIArmada\Contacting\Support\ContactingModelReferenceGuard;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $snapshotable_type
 * @property string|null $snapshotable_id
 * @property string $snapshot_type
 * @property string|null $source_id
 * @property string|null $source_type
 * @property string|null $reason
 * @property string|null $label
 * @property string|null $channel
 * @property string|null $value
 * @property string|null $normalized_value
 * @property string|null $url
 * @property string|null $display_value
 * @property bool $is_public
 * @property array|null $payload
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $snapshotable
 * @property-read Model|Eloquent $owner
 */
final class ContactSnapshot extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $fillable = [
        'snapshotable_type',
        'snapshotable_id',
        'snapshot_type',
        'source_id',
        'source_type',
        'reason',
        'label',
        'channel',
        'value',
        'normalized_value',
        'url',
        'display_value',
        'is_public',
        'payload',
        'metadata',
    ];

    protected static string $ownerScopeConfigKey = 'contacting.features.owner';

    public function getTable(): string
    {
        return config('contacting.database.tables.contact_snapshots', 'contact_snapshots');
    }

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'payload' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function snapshotable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfSnapshotType(Builder $query, string $snapshotType): Builder
    {
        return $query->where('snapshot_type', $snapshotType);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForReason(Builder $query, string $reason): Builder
    {
        return $query->where('reason', $reason);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    protected static function booted(): void
    {
        static::saving(function (ContactSnapshot $snapshot): void {
            $snapshot->guardSnapshotableOwner();
        });
    }

    private function guardSnapshotableOwner(): void
    {
        app(ContactingModelReferenceGuard::class)->resolve(
            $this->snapshotable_type,
            $this->snapshotable_id,
        );
    }

    protected static function newFactory(): ContactSnapshotFactory
    {
        return ContactSnapshotFactory::new();
    }
}
