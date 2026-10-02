<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Support\Links\AffiliateLinkDestination;
use AIArmada\Links\Actions\UpdateLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

final class UpdateTrackingLink
{
    use AsAction;

    /** @param array<string, mixed> $attributes */
    public function handle(AffiliateLink $link, array $attributes): AffiliateLink
    {
        return DB::transaction(function () use ($link, $attributes): AffiliateLink {
            $link = AffiliateLink::query()->whereKey($link->getKey())->lockForUpdate()->firstOrFail();
            if (isset($attributes['affiliate_id']) && $attributes['affiliate_id'] !== $link->affiliate_id) {
                throw ValidationException::withMessages(['affiliate_id' => 'An issued link cannot be reassigned.']);
            }
            Validator::make($attributes, ['destination_url' => ['sometimes', 'required', 'string', 'max:2000'], 'tracking_url' => ['prohibited'], 'short_url' => ['prohibited'], 'custom_slug' => ['prohibited']])->validate();
            $destination = $attributes['destination_url'] ?? $link->destination_url;
            $tracked = $link->trackedLink()->firstOrFail();
            UpdateLink::run($tracked, ['destination_url' => $destination], false, AffiliateLinkDestination::allowedHosts($destination));
            $link->update($attributes);

            return $link;
        });
    }
}
