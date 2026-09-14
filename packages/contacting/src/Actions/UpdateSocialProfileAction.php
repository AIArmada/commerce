<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Data\SocialProfileData;
use AIArmada\Contacting\Models\SocialProfile;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

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
        if (is_array($data)) {
            $data = SocialProfileData::from($data);
        }

        SocialProfileData::validate($data->toArray());

        $result = $this->normalizer->execute(
            $data->platform,
            $data->handle instanceof Optional ? $profile->handle : $data->handle,
            $data->url instanceof Optional ? $profile->url : $data->url,
        );

        DB::transaction(function () use ($profile, $data, $result): void {
            $profile->platform = $data->platform;

            if (! $data->purpose instanceof Optional) {
                $profile->purpose = $data->purpose;
            }

            $profile->label = $data->label instanceof Optional ? $profile->label : $data->label;
            $profile->handle = $result['handle'];
            $profile->url = $result['normalized_url'] ?? $profile->url;
            $profile->normalized_url = $result['normalized_url'];
            $profile->display_name = $data->displayName instanceof Optional ? $profile->display_name : $data->displayName;
            $profile->external_id = $data->externalId instanceof Optional ? $profile->external_id : $data->externalId;

            if (! $data->isPrimary instanceof Optional) {
                $profile->is_primary = $data->isPrimary;
            }

            $profile->is_public = $data->isPublic ?? $profile->is_public;

            if (! $data->isVerified instanceof Optional) {
                $profile->is_verified = $data->isVerified;
            }

            if ($data->verifiedAt !== null && ! $data->verifiedAt instanceof Optional) {
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

            if (! $data->metadata instanceof Optional) {
                $profile->metadata = $data->metadata;
            }

            $profile->save();
        });

        return $profile->refresh();
    }
}
