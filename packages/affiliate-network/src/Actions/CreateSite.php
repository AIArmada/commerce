<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Actions\Concerns\ValidatesSiteInput;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

final class CreateSite
{
    use ValidatesSiteInput;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): AffiliateSite
    {
        if (is_string($data['domain'] ?? null)) {
            $data['domain'] = mb_strtolower(mb_trim($data['domain']));
        }

        $validated = Validator::make($data, self::siteRules(), self::siteValidationMessages())->validate();
        $validated = self::normalizeSiteData($validated);
        $validated['status'] ??= AffiliateSite::STATUS_PENDING;

        if ($validated['status'] === AffiliateSite::STATUS_VERIFIED && empty($validated['verified_at'])) {
            $validated['verified_at'] = CarbonImmutable::now();
        }

        return AffiliateSite::query()->create($validated);
    }
}
