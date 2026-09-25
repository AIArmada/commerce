<?php

declare(strict_types=1);

namespace AIArmada\Links\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Links\Models\Concerns\HasSubject;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $link_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property CarbonImmutable $occurred_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $device_type
 * @property string|null $device_brand
 * @property string|null $device_model
 * @property string|null $browser
 * @property string|null $browser_version
 * @property string|null $os
 * @property string|null $os_version
 * @property bool $is_bot
 * @property string|null $referrer
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_content
 * @property string|null $utm_term
 * @property array<string, mixed>|null $properties
 * @property-read Link $link
 */
final class LinkClick extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasSubject;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'links.owner';

    /** @var list<string> */
    protected $fillable = [
        'link_id',
        'subject_type',
        'subject_id',
        'occurred_at',
        'ip_address',
        'user_agent',
        'device_type',
        'device_brand',
        'device_model',
        'browser',
        'browser_version',
        'os',
        'os_version',
        'is_bot',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'properties',
        'owner_type',
        'owner_id',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_bot' => false,
    ];

    /** @var array<string, string> */
    protected $casts = [
        'occurred_at' => 'immutable_datetime',
        'is_bot' => 'boolean',
        'properties' => 'array',
    ];

    public function getTable(): string
    {
        $tables = config('links.database.tables', []);
        $prefix = config('links.database.table_prefix', '');

        return $tables['clicks'] ?? $prefix . 'tracked_link_clicks';
    }

    /**
     * @return BelongsTo<Link, $this>
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class, 'link_id');
    }
}
