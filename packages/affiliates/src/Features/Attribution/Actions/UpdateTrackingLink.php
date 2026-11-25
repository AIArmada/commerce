<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Actions;

use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Support\Links\AffiliateLinkDestination;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Links\Actions\UpdateLink;
use AIArmada\Links\Models\Link;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class UpdateTrackingLink
{
    /** @param array<string, mixed> $attributes */
    public function handle(string $linkId, array $attributes): AffiliateLink
    {
        $connection = (new AffiliateLink)->getConnection();
        if ($connection !== (new Link)->getConnection()) {
            throw new LogicException('Tracking link update requires a shared database connection.');
        }

        return $connection->transaction(function () use ($linkId, $attributes): AffiliateLink {
            $link = AffiliateLink::query()->lockForUpdate()->findOrFail($linkId);
            if (isset($attributes['affiliate_id']) && $attributes['affiliate_id'] !== $link->affiliate_id) {
                throw ValidationException::withMessages(['affiliate_id' => 'An issued link cannot be reassigned.']);
            }
            Validator::make($attributes, ['destination_url' => ['sometimes', 'required', 'string', 'max:2000'], 'tracking_url' => ['prohibited'], 'short_url' => ['prohibited'], 'custom_slug' => ['prohibited']])->validate();
            $attributes = Arr::only($attributes, ['program_id', 'destination_url', 'campaign', 'sub_id', 'sub_id_2', 'sub_id_3']);
            if (isset($attributes['program_id'])) {
                if (config('affiliates.owner.enabled', false)) {
                    OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $attributes['program_id'], includeGlobal: true);
                } else {
                    AffiliateProgram::query()->findOrFail($attributes['program_id']);
                }
            }
            if (! $link->affiliate->isCommercialPurpose()) {
                unset($attributes['destination_url']);
            }
            $destination = $attributes['destination_url'] ?? $link->destination_url;
            $tracked = $link->trackedLink()->firstOrFail();
            UpdateLink::run($tracked, ['destination_url' => $destination], false, AffiliateLinkDestination::allowedHosts($destination));
            $link->update($attributes);

            return $link;
        });
    }
}
