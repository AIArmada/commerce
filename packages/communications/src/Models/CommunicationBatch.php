<?php

declare(strict_types=1);

namespace AIArmada\Communications\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $purpose
 * @property string $category
 * @property string $status
 * @property string|null $idempotency_key
 * @property string|null $laravel_batch_id
 * @property int $requested_count
 * @property int $planned_count
 * @property int $queued_count
 * @property int $completed_count
 * @property int $failed_count
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $expires_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Communication> $communications
 * @property-read Model|Eloquent $owner
 */
final class CommunicationBatch extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $fillable = [
        'name',
        'purpose',
        'category',
        'status',
        'idempotency_key',
        'laravel_batch_id',
        'requested_count',
        'planned_count',
        'queued_count',
        'completed_count',
        'failed_count',
        'scheduled_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'failed_at',
        'expires_at',
        'metadata',
    ];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.batches', 'communication_batches');
    }

    protected function casts(): array
    {
        return [
            'requested_count' => 'integer',
            'planned_count' => 'integer',
            'queued_count' => 'integer',
            'completed_count' => 'integer',
            'failed_count' => 'integer',
            'scheduled_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<Communication, $this>
     */
    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class, 'batch_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (CommunicationBatch $batch): void {
            $batch->communications()->each(fn (Communication $c) => $c->delete());
        });
    }
}
