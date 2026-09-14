<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Data\SocialProfileData;
use AIArmada\Contacting\Models\SocialProfile;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

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
        if (is_array($data)) {
            $data = SocialProfileData::from($data);
        }

        SocialProfileData::validate($data->toArray());

        $result = $this->normalizer->execute(
            $data->platform,
            $data->handle instanceof Optional ? null : $data->handle,
            $data->url instanceof Optional ? null : $data->url,
        );

        $profile = new SocialProfile;
        $profile->socialable()->associate($socialable);
        $profile->platform = $data->platform;
        $profile->purpose = $data->purpose instanceof Optional ? 'general' : $data->purpose;
        $profile->label = $data->label instanceof Optional ? null : $data->label;
        $profile->handle = $result['handle'];
        $profile->url = $result['normalized_url'];
        $profile->normalized_url = $result['normalized_url'];
        $profile->display_name = $data->displayName instanceof Optional ? null : $data->displayName;
        $profile->external_id = $data->externalId instanceof Optional ? null : $data->externalId;
        $profile->is_primary = ! $data->isPrimary instanceof Optional && $data->isPrimary;
        $profile->is_public = $data->isPublic;
        $profile->is_verified = ! $data->isVerified instanceof Optional && $data->isVerified;
        $profile->verified_at = $data->verifiedAt instanceof Optional ? null : $data->verifiedAt;
        $profile->valid_from = self::toImmutable($data->validFrom);
        $profile->valid_until = self::toImmutable($data->validUntil);
        $profile->metadata = $data->metadata instanceof Optional ? [] : $data->metadata;

        if ($data->sortOrder !== null) {
            $profile->sort_order = $data->sortOrder;
        }

        DB::transaction(function () use ($profile): void {
            $profile->save();
        });

        return $profile;
    }
}
