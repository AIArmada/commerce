<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Exceptions\AffiliateNotFoundException;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateTrackingLink
{
    use AsAction;

    public function __construct(
        private readonly AffiliateLinkGenerator $linkGenerator,
    ) {}

    public function handle(Affiliate $affiliate, string $destinationUrl, array $attributes = []): AffiliateLink
    {
        if (! $affiliate->isActive()) {
            throw new AffiliateNotFoundException("Affiliate {$affiliate->code} is not active.");
        }

        $programId = Arr::get($attributes, 'program_id');

        if (is_string($programId) || is_int($programId)) {
            $program = OwnerWriteGuard::findOrFailForOwner(
                AffiliateProgram::class,
                $programId,
                includeGlobal: true,
                message: 'Selected program is not accessible in the current owner scope.',
            );

            $programId = (string) $program->getKey();
        }

        $params = Arr::get($attributes, 'params', []);

        if (! is_array($params)) {
            $params = [];
        }

        $subjectMetadata = Arr::get($attributes, 'subject_metadata');

        if (! is_array($subjectMetadata)) {
            $subjectMetadata = null;
        }

        // Some hosts already have a canonical, signed, or otherwise product-specific
        // tracking URL. Keep link creation reusable without forcing those hosts to
        // duplicate the package's persistence workflow.
        $trackingUrl = Arr::get($attributes, 'tracking_url');

        if (! is_string($trackingUrl) || mb_trim($trackingUrl) === '') {
            $trackingUrl = $this->linkGenerator->generate(
                affiliateCode: $affiliate->code,
                url: $destinationUrl,
                params: $params,
                ttlSeconds: is_int(Arr::get($attributes, 'ttl_seconds')) ? Arr::get($attributes, 'ttl_seconds') : null,
            );
        } elseif (! in_array(mb_strtolower((string) parse_url($trackingUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new InvalidArgumentException('Tracking URL scheme must be http or https.');
        }

        return AffiliateLink::query()->create([
            'affiliate_id' => $affiliate->getKey(),
            'program_id' => $programId,
            'destination_url' => $destinationUrl,
            'tracking_url' => $trackingUrl,
            'short_url' => Arr::get($attributes, 'short_url'),
            'custom_slug' => Arr::get($attributes, 'custom_slug'),
            'campaign' => Arr::get($attributes, 'campaign'),
            'sub_id' => Arr::get($attributes, 'sub_id'),
            'sub_id_2' => Arr::get($attributes, 'sub_id_2'),
            'sub_id_3' => Arr::get($attributes, 'sub_id_3'),
            'subject_type' => Arr::get($attributes, 'subject_type'),
            'subject_identifier' => Arr::get($attributes, 'subject_identifier'),
            'subject_instance' => Arr::get($attributes, 'subject_instance'),
            'subject_title_snapshot' => Str::limit((string) Arr::get($attributes, 'subject_title_snapshot', ''), 200, ''),
            'subject_metadata' => $subjectMetadata,
            'deactivated_at' => Arr::get($attributes, 'deactivated_at'),
        ]);
    }
}
