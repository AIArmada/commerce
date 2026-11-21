<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Actions\Concerns\ValidatesSiteInput;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

final class UpdateSite
{
    use ValidatesSiteInput;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AffiliateSite $site, array $data): AffiliateSite
    {
        if (is_string($data['domain'] ?? null)) {
            $data['domain'] = mb_strtolower(mb_trim($data['domain']));
        }

        $site = AffiliateSite::query()->whereKey($site->getKey())->firstOrFail();
        $validated = Validator::make($data, self::siteRules(), self::siteValidationMessages())->validate();
        $validated = self::normalizeSiteData($validated);

        if (array_key_exists('status', $validated)) {
            $validated['verified_at'] = $validated['status'] === AffiliateSite::STATUS_VERIFIED
                ? ($validated['verified_at'] ?? $site->verified_at ?? CarbonImmutable::now())
                : null;
        }

        $site->update($validated);

        return $site->refresh();
    }
}
