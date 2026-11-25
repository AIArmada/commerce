<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Actions;

use AIArmada\Affiliates\Features\Attribution\Models\AffiliateLink;
use AIArmada\Affiliates\Features\Attribution\Support\AffiliateLinkDestination;
use AIArmada\Affiliates\Features\Programs\Api\TrackingProgramGuard;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Links\Actions\UpdateLink;
use AIArmada\Links\Models\Link;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class UpdateTrackingLink
{
    public function __construct(private TrackingProgramGuard $programs) {}

    /** @param array<string, mixed> $attributes */
    public function handle(string $linkId, array $attributes): AffiliateLink
    {
        $connection = (new AffiliateLink)->getConnection();
        if ($connection !== (new Link)->getConnection() || $connection !== (new Affiliate)->getConnection()) {
            throw new LogicException('Tracking link update requires a shared database connection.');
        }

        return $connection->transaction(function () use ($linkId, $attributes): AffiliateLink {
            $reference = AffiliateLink::query()->findOrFail($linkId);
            $affiliate = Affiliate::query()->forOwner()->lockForUpdate()->findOrFail($reference->affiliate_id);
            $link = AffiliateLink::query()->where('affiliate_id', $affiliate->getKey())->lockForUpdate()->findOrFail($linkId);
            if (array_key_exists('affiliate_id', $attributes) && $attributes['affiliate_id'] !== $link->affiliate_id) {
                throw ValidationException::withMessages(['affiliate_id' => 'An issued link cannot be reassigned.']);
            }
            Validator::make($attributes, ['program_id' => ['nullable', 'string', 'uuid'], 'deactivated_at' => ['nullable', 'date'], 'destination_url' => ['sometimes', 'required', 'string', 'max:2048'], 'tracking_url' => ['prohibited'], 'short_url' => ['prohibited'], 'custom_slug' => ['prohibited']])->validate();
            $attributes = Arr::only($attributes, ['program_id', 'destination_url', 'campaign', 'sub_id', 'sub_id_2', 'sub_id_3', 'deactivated_at']);
            if (isset($attributes['program_id'])) {
                $this->programs->lockForTracking($attributes['program_id'], spl_object_id($link->getConnection()));
            }
            if (! $affiliate->isCommercialPurpose()) {
                unset($attributes['destination_url']);
            }
            $destination = $attributes['destination_url'] ?? $link->destination_url;
            $tracked = $link->trackedLink()->lockForUpdate()->firstOrFail();
            if ($destination !== $link->destination_url) {
                UpdateLink::run($tracked, ['destination_url' => $destination], false, AffiliateLinkDestination::allowedHosts($destination));
            }
            $link->update($attributes);

            return $link;
        });
    }
}
