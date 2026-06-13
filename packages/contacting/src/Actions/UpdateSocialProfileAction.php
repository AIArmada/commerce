<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Data\SocialProfileData;
use AIArmada\Contacting\Models\SocialProfile;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

final class UpdateSocialProfileAction
{
    public function __construct(
        private readonly NormalizeSocialProfileAction $normalizer,
    ) {}

    public function execute(SocialProfile $profile, SocialProfileData | array $data): SocialProfile
    {
        if (is_array($data)) {
            $data = SocialProfileData::from($data);
        }

        $result = $this->normalizer->execute(
            $data->platform,
            $data->handle instanceof Optional ? $profile->handle : $data->handle,
            $data->url instanceof Optional ? $profile->url : $data->url,
        );

        DB::transaction(function () use ($profile, $data, $result): void {
            $profile->platform = $data->platform;
            $profile->purpose = $data->purpose;
            $profile->label = $data->label instanceof Optional ? $profile->label : $data->label;
            $profile->handle = $result['handle'];
            $profile->url = $result['normalized_url'] ?? $profile->url;
            $profile->normalized_url = $result['normalized_url'];
            $profile->display_name = $data->displayName instanceof Optional ? $profile->display_name : $data->displayName;
            $profile->external_id = $data->externalId instanceof Optional ? $profile->external_id : $data->externalId;
            $profile->is_primary = $data->isPrimary;
            $profile->is_public = $data->isPublic;
            $profile->is_verified = $data->isVerified;
            $profile->metadata = $data->metadata;

            $profile->save();
        });

        return $profile->refresh();
    }
}
