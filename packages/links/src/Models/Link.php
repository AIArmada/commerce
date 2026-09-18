<?php

declare(strict_types=1);

namespace AIArmada\Links\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Links\Contracts\SlugGeneratorInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string $destination_url
 * @property array<string, string|null>|null $utm_defaults
 * @property int|null $max_clicks
 * @property int $total_clicks
 * @property int $human_clicks
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $deactivated_at
 * @property CarbonImmutable|null $first_clicked_at
 * @property CarbonImmutable|null $last_clicked_at
 * @property-read Collection<int, LinkClick> $clicks
 */
final class Link extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'links.owner';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'destination_url',
        'utm_defaults',
        'max_clicks',
        'total_clicks',
        'human_clicks',
        'expires_at',
        'deactivated_at',
        'first_clicked_at',
        'last_clicked_at',
        'owner_type',
        'owner_id',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'total_clicks' => 0,
        'human_clicks' => 0,
    ];

    /** @var array<string, string> */
    protected $casts = [
        'utm_defaults' => 'array',
        'max_clicks' => 'integer',
        'total_clicks' => 'integer',
        'human_clicks' => 'integer',
        'expires_at' => 'immutable_datetime',
        'deactivated_at' => 'immutable_datetime',
        'first_clicked_at' => 'immutable_datetime',
        'last_clicked_at' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        $tables = config('links.database.tables', []);
        $prefix = config('links.database.table_prefix', '');

        return $tables['links'] ?? $prefix . 'tracked_links';
    }

    /**
     * @return HasMany<LinkClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(LinkClick::class, 'link_id');
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null
            && ! $this->isExpired()
            && ! $this->hasReachedClickLimit();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasReachedClickLimit(): bool
    {
        return $this->max_clicks !== null && $this->human_clicks >= $this->max_clicks;
    }

    public function cloakedUrl(): string
    {
        return route((string) config('links.routing.name', 'links.redirect'), ['slug' => $this->slug]);
    }

    protected static function booted(): void
    {
        static::creating(function (Link $link): void {
            if (! is_string($link->slug) || $link->slug === '') {
                $link->slug = static::generateUniqueSlug();
            }
        });

        static::deleting(function (Link $link): void {
            $link->clicks()->delete();
        });
    }

    private static function generateUniqueSlug(): string
    {
        $generator = app(SlugGeneratorInterface::class);
        $length = (int) config('links.defaults.slug_length', 7);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $slug = $generator->generate($length);

            if (! static::query()->withoutOwnerScope()->where('slug', $slug)->exists()) {
                return $slug;
            }
        }

        throw new RuntimeException('Unable to generate a unique link slug.');
    }
}
