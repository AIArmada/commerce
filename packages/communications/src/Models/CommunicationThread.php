<?php

declare(strict_types=1);

namespace AIArmada\Communications\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Communications\Enums\ThreadStatus;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $external_thread_id
 * @property string $channel
 * @property string|null $title
 * @property ThreadStatus $status
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $last_communication_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $archived_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $subject
 * @property-read Collection<int, Communication> $communications
 * @property-read Model|Eloquent $owner
 */
final class CommunicationThread extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $guarded = [];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.threads', 'communication_threads');
    }

    protected function casts(): array
    {
        return [
            'status' => ThreadStatus::class,
            'opened_at' => 'immutable_datetime',
            'last_communication_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<Communication, $this>
     */
    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class, 'thread_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (CommunicationThread $thread): void {
            $thread->communications()->each(fn (Communication $c) => $c->delete());
        });
    }
}
