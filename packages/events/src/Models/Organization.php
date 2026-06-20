<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Contacting\Concerns\HasContactMethods;
use AIArmada\Contacting\Concerns\HasSocialProfiles;
use AIArmada\Events\Contracts\CanBeInvolvedInEvents;
use AIArmada\Events\Contracts\OwnsEvents;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use AIArmada\Events\Traits\CanOrganizeEvents;
use AIArmada\Events\Traits\HasEventInvolvements;
use AIArmada\Events\Traits\OwnsEvents as OwnsEventsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string|null $bio
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website_url
 * @property string $status
 * @property string $visibility
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Organization extends Model implements CanBeInvolvedInEvents, HasMedia, OwnsEvents
{
    use CanOrganizeEvents;
    use HasContactMethods;
    use HasEventInvolvements;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasSocialProfiles;
    use InteractsWithMedia;
    use OwnsEventsTrait;
    use UsesEventUuid;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'name', 'slug', 'bio',
        'status', 'visibility',
        'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.organizations', 'organizations');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function eventDisplayName(): string
    {
        return $this->eventOrganizerName();
    }

    public function eventDisplaySubtitle(): ?string
    {
        return null;
    }

    public function eventDisplayImage(): ?string
    {
        return $this->getFirstMediaUrl('logo') ?: null;
    }

    public function eventProfileUrl(): ?string
    {
        return $this->eventOrganizerProfileUrl();
    }

    public function getEmailAttribute(): ?string
    {
        return $this->resolvePrimaryContactValue('email');
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->resolvePrimaryContactValue('phone');
    }

    public function getWebsiteUrlAttribute(): ?string
    {
        return $this->resolvePrimaryContactValue('website');
    }

    private function resolvePrimaryContactValue(string $type): ?string
    {
        $contactMethod = $this->primaryContactMethod($type);

        if ($contactMethod === null) {
            return null;
        }

        $value = $contactMethod->normalized_value ?? $contactMethod->value;

        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }
}
