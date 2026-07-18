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
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $recipient_type
 * @property string $recipient_id
 * @property string $channel
 * @property string|null $address
 * @property string|null $external_id
 * @property string $status
 * @property bool $is_primary
 * @property CarbonImmutable|null $verified_at
 * @property array|null $metadata
 * @property string|null $platform
 * @property string|null $app_version
 * @property string|null $device_label
 * @property string|null $locale
 * @property string|null $timezone
 * @property CarbonImmutable|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $recipient
 * @property-read Model|Eloquent $owner
 */
final class CommunicationDestination extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $fillable = [
        'recipient_type',
        'recipient_id',
        'channel',
        'address',
        'external_id',
        'status',
        'is_primary',
        'verified_at',
        'metadata',
        'platform',
        'app_version',
        'device_label',
        'locale',
        'timezone',
        'last_seen_at',
    ];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.destinations', 'communication_destinations');
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'immutable_datetime',
            'metadata' => 'array',
            'last_seen_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }
}
