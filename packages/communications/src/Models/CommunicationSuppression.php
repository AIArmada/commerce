<?php

declare(strict_types=1);

namespace AIArmada\Communications\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Communications\Enums\SuppressionReason;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $recipient_type
 * @property string|null $recipient_id
 * @property string $destination_hash
 * @property string $channel
 * @property string|null $category
 * @property SuppressionReason $reason
 * @property string|null $source
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $lifted_at
 * @property string|null $created_by_type
 * @property string|null $created_by_id
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $recipient
 * @property-read Model|Eloquent $createdBy
 * @property-read Model|Eloquent $owner
 */
final class CommunicationSuppression extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $guarded = [];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.suppressions', 'communication_suppressions');
    }

    protected function casts(): array
    {
        return [
            'reason' => SuppressionReason::class,
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'lifted_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function createdBy(): MorphTo
    {
        return $this->morphTo('created_by');
    }
}
