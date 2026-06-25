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
 * @property string|null $recipient_type
 * @property string|null $recipient_id
 * @property string $channel
 * @property string|null $category
 * @property string|null $locale
 * @property string|null $timezone
 * @property string|null $quiet_hours_start
 * @property string|null $quiet_hours_end
 * @property string|null $quiet_hours_timezone
 * @property CarbonImmutable|null $enabled_at
 * @property CarbonImmutable|null $disabled_at
 * @property CarbonImmutable|null $opted_in_at
 * @property CarbonImmutable|null $opted_out_at
 * @property CarbonImmutable|null $verified_at
 * @property string|null $source
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $recipient
 * @property-read Model|Eloquent $owner
 */
final class CommunicationPreference extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $fillable = [
        'recipient_type',
        'recipient_id',
        'channel',
        'category',
        'locale',
        'timezone',
        'quiet_hours_start',
        'quiet_hours_end',
        'quiet_hours_timezone',
        'enabled_at',
        'disabled_at',
        'opted_in_at',
        'opted_out_at',
        'verified_at',
        'source',
        'metadata',
    ];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.preferences', 'communication_preferences');
    }

    protected function casts(): array
    {
        return [
            'enabled_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
            'opted_in_at' => 'immutable_datetime',
            'opted_out_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
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
}
