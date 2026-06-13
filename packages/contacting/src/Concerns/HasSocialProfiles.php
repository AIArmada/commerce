<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Concerns;

use AIArmada\Contacting\Actions\CreateSocialProfileAction;
use AIArmada\Contacting\Data\SocialProfileData;
use AIArmada\Contacting\Models\SocialProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSocialProfiles
{
    protected static function bootHasSocialProfiles(): void
    {
        static::deleting(function (Model $model): void {
            /** @phpstan-ignore-next-line */
            $model->socialProfiles()->delete();
        });
    }

    /**
     * @return MorphMany<SocialProfile, $this>
     */
    public function socialProfiles(): MorphMany
    {
        return $this->morphMany(SocialProfile::class, 'socialable');
    }

    /**
     * @return MorphMany<SocialProfile, $this>
     */
    public function publicSocialProfiles(): MorphMany
    {
        return $this->socialProfiles()->where('is_public', true);
    }

    public function primarySocialProfile(?string $platform = null, ?string $purpose = null): ?SocialProfile
    {
        return $this->socialProfiles()
            ->when($platform !== null, fn ($query) => $query->where('platform', $platform))
            ->when($purpose !== null, fn ($query) => $query->where('purpose', $purpose))
            ->where('is_primary', true)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * @return MorphMany<SocialProfile, $this>
     */
    public function socialProfilesForPlatform(string $platform): MorphMany
    {
        return $this->socialProfiles()->where('platform', $platform);
    }

    public function addSocialProfile(SocialProfileData | array $data): SocialProfile
    {
        if (is_array($data)) {
            $data = SocialProfileData::from($data);
        }

        return app(CreateSocialProfileAction::class)
            ->execute($this, $data);
    }
}
