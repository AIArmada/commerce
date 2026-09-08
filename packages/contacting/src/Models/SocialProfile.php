<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Contacting\Actions\NormalizeSocialProfileAction;
use AIArmada\Contacting\Database\Factories\SocialProfileFactory;
use AIArmada\Contacting\Enums\SocialPlatform;
use AIArmada\Contacting\Support\ContactingModelReferenceGuard;
use AIArmada\Contacting\Support\SocialProfileConfig;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $socialable_type
 * @property string|null $socialable_id
 * @property string $platform
 * @property string $purpose
 * @property string|null $label
 * @property string|null $handle
 * @property string|null $url
 * @property string|null $normalized_url
 * @property string|null $display_name
 * @property string|null $external_id
 * @property bool $is_primary
 * @property bool $is_public
 * @property bool $is_verified
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $valid_from
 * @property CarbonImmutable|null $valid_until
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $socialable
 * @property-read Model|Eloquent $owner
 */
final class SocialProfile extends Model
{
    private bool $shouldSyncPrimary = false;

    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $fillable = [
        'socialable_type',
        'socialable_id',
        'platform',
        'purpose',
        'label',
        'handle',
        'url',
        'normalized_url',
        'display_name',
        'external_id',
        'is_primary',
        'is_public',
        'is_verified',
        'verified_at',
        'valid_from',
        'valid_until',
        'sort_order',
        'metadata',
    ];

    protected static string $ownerScopeConfigKey = 'contacting.features.owner';

    public function getTable(): string
    {
        return config('contacting.database.tables.social_profiles', 'social_profiles');
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_public' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'immutable_datetime',
            'valid_from' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function profileUrl(): ?string
    {
        $handle = $this->handle;

        if ($handle === null || $handle === '') {
            return $this->url;
        }

        return app(SocialProfileConfig::class)->buildUrl($this->platform, $handle) ?? $this->url;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function socialable(): MorphTo
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
    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForPurpose(Builder $query, string $purpose): Builder
    {
        return $query->where('purpose', $purpose);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    protected static function booted(): void
    {
        static::creating(function (SocialProfile $profile): void {
            $profile->applyDefaultFlags();
        });

        static::saving(function (SocialProfile $profile): void {
            $profile->normalizeForSave();
            $profile->guardAllowedPlatform();
            $profile->guardSocialableOwner();
            $profile->shouldSyncPrimary = $profile->isDirty('is_primary')
                && $profile->is_primary;
        });

        static::saved(function (SocialProfile $profile): void {
            $profile->syncSiblingPrimaryFlags();
        });
    }

    private function applyDefaultFlags(): void
    {
        if ($this->is_public === null) {
            $this->is_public = (bool) config('contacting.defaults.public_by_default', true);
        }

        if ($this->is_verified === null) {
            $this->is_verified = (bool) config('contacting.defaults.verified_by_default', false);
        }
    }

    private function normalizeForSave(): void
    {
        $handleWasExplicitlyChanged = $this->exists && $this->isDirty('handle');
        $urlWasExplicitlyChanged = $this->exists && $this->isDirty('url');

        $normalized = app(NormalizeSocialProfileAction::class)->execute(
            $this->platform,
            $this->handle,
            $this->url,
        );

        if (! $handleWasExplicitlyChanged
            && (! $this->exists || $this->isDirty('url') || $this->handle === null)) {
            $this->handle = $normalized['handle'];
        }

        if (! $urlWasExplicitlyChanged
            && (! $this->exists || $this->isDirty('handle') || $this->url === null)) {
            $this->url = $normalized['normalized_url'];
        }

        $this->normalized_url = $normalized['normalized_url'];
    }

    private function guardAllowedPlatform(): void
    {
        if (! (bool) config('contacting.features.strict_social_platforms', false)) {
            return;
        }

        if (SocialPlatform::tryFrom($this->platform) === null) {
            throw new InvalidArgumentException(sprintf('Unsupported social platform "%s".', $this->platform));
        }
    }

    private function syncSiblingPrimaryFlags(): void
    {
        if (! $this->shouldSyncPrimary) {
            return;
        }

        DB::transaction(function (): void {
            if ($this->socialable_type === null || $this->socialable_id === null) {
                return;
            }

            $this->socialable()->lockForUpdate()->firstOrFail();

            // saving() validates the socialable and owner before this demotion query runs.
            SocialProfile::query()
                ->where('socialable_type', $this->socialable_type)
                ->where('socialable_id', $this->socialable_id)
                ->where('platform', $this->platform)
                ->where('purpose', $this->purpose)
                ->whereKeyNot($this->getKey())
                ->where(function (Builder $query): void {
                    if ($this->owner_type === null) {
                        $query->whereNull('owner_type')->whereNull('owner_id');

                        return;
                    }

                    $query->where('owner_type', $this->owner_type)
                        ->where('owner_id', $this->owner_id);
                })
                ->lockForUpdate()
                ->update(['is_primary' => false]);
        });
    }

    private function guardSocialableOwner(): void
    {
        app(ContactingModelReferenceGuard::class)->resolve(
            $this->socialable_type,
            $this->socialable_id,
        );
    }

    protected static function newFactory(): SocialProfileFactory
    {
        return SocialProfileFactory::new();
    }
}
