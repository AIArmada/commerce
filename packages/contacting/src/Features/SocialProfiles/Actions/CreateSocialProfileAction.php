<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\SocialProfiles\Actions;

use AIArmada\Contacting\Data\SocialProfileData;
use AIArmada\Contacting\Features\SocialProfiles\Models\SocialProfile;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use AIArmada\Contacting\Data\MissingValue;

final class CreateSocialProfileAction
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

    public function execute(Model $socialable, SocialProfileData | array $data): SocialProfile
    {
        $data = app(ValidateSocialProfileData::class)->execute($data);

        $result = $this->normalizer->execute(
            $data->platform,
            $data->handle instanceof MissingValue ? null : $data->handle,
            $data->url instanceof MissingValue ? null : $data->url,
        );

        $profile = new SocialProfile;
        $profile->socialable()->associate($socialable);
        $profile->platform = $data->platform;
        $profile->purpose = $data->purpose instanceof MissingValue ? 'general' : $data->purpose;
        $profile->label = $data->label instanceof MissingValue ? null : $data->label;
        $profile->handle = $result['handle'];
        $profile->url = $result['normalized_url'];
        $profile->normalized_url = $result['normalized_url'];
        $profile->display_name = $data->displayName instanceof MissingValue ? null : $data->displayName;
        $profile->external_id = $data->externalId instanceof MissingValue ? null : $data->externalId;
        $profile->is_primary = ! $data->isPrimary instanceof MissingValue && $data->isPrimary;
        $profile->is_public = $data->isPublic;
        $profile->is_verified = ! $data->isVerified instanceof MissingValue && $data->isVerified;
        $profile->verified_at = $data->verifiedAt instanceof MissingValue ? null : $data->verifiedAt;
        $profile->valid_from = self::toImmutable($data->validFrom);
        $profile->valid_until = self::toImmutable($data->validUntil);
        $profile->metadata = $data->metadata instanceof MissingValue ? [] : $data->metadata;

        if ($data->sortOrder !== null) {
            $profile->sort_order = $data->sortOrder;
        }

        $profile->getConnection()->transaction(function () use ($profile): void {
            if (! $profile->save()) {
                throw new \RuntimeException('The contact write was cancelled.');
            }
        });

        return $profile;
    }
}
