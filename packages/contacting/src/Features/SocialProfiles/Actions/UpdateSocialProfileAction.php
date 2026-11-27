<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\SocialProfiles\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Contacting\Data\SocialProfileData;
use AIArmada\Contacting\Features\SocialProfiles\Models\SocialProfile;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use AIArmada\Contacting\Data\MissingValue;

final class UpdateSocialProfileAction
{
    public function __construct(
        private readonly NormalizeSocialProfileAction $normalizer,
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
        $profile = SocialProfile::ownerScopeConfig()->enabled
            ? OwnerWriteGuard::findOrFailForOwner(SocialProfile::class, $profile->getKey())
            : SocialProfile::query()->whereKey($profile->getKey())->firstOrFail();

        $data = app(ValidateSocialProfileData::class)->execute($data);

        $result = $this->normalizer->execute(
            $data->platform,
            $data->handle instanceof MissingValue ? $profile->handle : $data->handle,
            $data->url instanceof MissingValue ? $profile->url : $data->url,
        );

        $profile->getConnection()->transaction(function () use ($profile, $data, $result): void {
            $profile->platform = $data->platform;

            if (! $data->purpose instanceof MissingValue) {
                $profile->purpose = $data->purpose;
            }

            $profile->label = $data->label instanceof MissingValue ? $profile->label : $data->label;
            $profile->handle = $result['handle'];
            $profile->url = $result['normalized_url'] ?? $profile->url;
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

            if (! $profile->save()) {
                throw new \RuntimeException('The contact write was cancelled.');
            }
        });

        return $profile->refresh();
    }
}
