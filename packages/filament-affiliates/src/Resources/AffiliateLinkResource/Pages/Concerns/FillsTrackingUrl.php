<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource\Pages\Concerns;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

trait FillsTrackingUrl
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillTrackingUrl(array $data): array
    {
        $trackingUrl = mb_trim((string) ($data['tracking_url'] ?? ''));

        if ($trackingUrl !== '') {
            $data['tracking_url'] = $trackingUrl;

            return $data;
        }

        $destinationUrl = mb_trim((string) ($data['destination_url'] ?? ''));
        $affiliateId = $data['affiliate_id'] ?? null;

        if ($destinationUrl === '' || (! is_string($affiliateId) && ! is_int($affiliateId))) {
            return $data;
        }

        $affiliate = Affiliate::query()->find($affiliateId);

        if ($affiliate === null) {
            return $data;
        }

        try {
            $data['tracking_url'] = app(AffiliateLinkGenerator::class)->generate(
                affiliateCode: $affiliate->code,
                url: $destinationUrl,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'data.destination_url' => $exception->getMessage(),
            ]);
        }

        return $data;
    }
}
