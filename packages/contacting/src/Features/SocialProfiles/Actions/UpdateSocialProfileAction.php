<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\SocialProfiles\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Contacting\Contracts\SocialProfileNormalizer;
use AIArmada\Contacting\Data\MissingValue;
use AIArmada\Contacting\Data\SocialProfileData;
use AIArmada\Contacting\Features\SocialProfiles\Models\SocialProfile;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use RuntimeException;

final class UpdateSocialProfileAction
{
    public function __construct(
        private readonly SocialProfileNormalizer $normalizer,
    ) {}

    private static function toImmutable(DateTimeInterface | string | null $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof CarbonImmutable ? $value : CarbonImmutable::parse($value);
    }

    public function execute(SocialProfile $profile, SocialProfileData | array $data): SocialProfile
    {
        $data = app(ValidateSocialProfileData::class)->execute($data);

        return $profile->getConnection()->transaction(function () use ($profile, $data): SocialProfile {
            if (SocialProfile::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(SocialProfile::class, $profile->getKey());
            }

            $parent = $profile->socialable()->lockForUpdate()->firstOrFail();
            $profile = SocialProfile::query()->whereKey($profile->getKey())->lockForUpdate()->firstOrFail();

            if ((string) $profile->socialable_id !== (string) $parent->getKey()
                || $profile->socialable_type !== $parent->getMorphClass()) {
                throw new RuntimeException('The social profile parent changed while locking.');
            }

            $handleProvided = ! $data->handle instanceof MissingValue;
            $urlProvided = ! $data->url instanceof MissingValue;
            $platformChanged = $data->platform !== $profile->platform;
            $handleChanged = $handleProvided && $data->handle !== $profile->handle;
            $urlChanged = $urlProvided && $data->url !== $profile->url;

            $handle = $data->handle instanceof MissingValue
                ? ($urlChanged ? null : $profile->handle)
                : $data->handle;
            $url = $data->url instanceof MissingValue
                ? ($handleChanged || $platformChanged ? null : $profile->url)
                : $data->url;

            $result = $this->normalizer->normalize($data->platform, $handle, $url);

            $profile->platform = $data->platform;

            if (! $data->purpose instanceof MissingValue) {
                $profile->purpose = $data->purpose;
            }

            $profile->label = $data->label instanceof MissingValue ? $profile->label : $data->label;
            $profile->handle = $result['handle'];
            $profile->url = $result['normalized_url'];
            $profile->normalized_url = $result['normalized_url'];
            $profile->display_name = $data->displayName instanceof MissingValue ? $profile->display_name : $data->displayName;
            $profile->external_id = $data->externalId instanceof MissingValue ? $profile->external_id : $data->externalId;

            if (! $data->isPrimary instanceof MissingValue) {
                $profile->is_primary = $data->isPrimary;
            }

            $profile->is_public = $data->isPublic ?? $profile->is_public;

            if (! $data->isVerified instanceof MissingValue) {
                $profile->is_verified = $data->isVerified;
            }

            if ($data->verifiedAt !== null && ! $data->verifiedAt instanceof MissingValue) {
                $profile->verified_at = $data->verifiedAt;
            }

            if ($data->validFrom !== null) {
                $profile->valid_from = self::toImmutable($data->validFrom);
            }

            if ($data->validUntil !== null) {
                $profile->valid_until = self::toImmutable($data->validUntil);
            }

            if ($data->sortOrder !== null) {
                $profile->sort_order = $data->sortOrder;
            }

            if (! $data->metadata instanceof MissingValue) {
                $profile->metadata = $data->metadata;
            }

            if (! app(SynchronizePrimarySocialProfileAction::class)->execute($profile)) {
                throw new RuntimeException('The contact write was cancelled.');
            }

            return $profile->refresh();
        }, attempts: 3);
    }
}
