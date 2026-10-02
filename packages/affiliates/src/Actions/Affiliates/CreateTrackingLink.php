<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Exceptions\AffiliateNotFoundException;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Support\Links\AffiliateLinkDestination;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Links\Actions\CreatePublicLink;
use AIArmada\Links\Actions\GenerateLinkUrl;
use AIArmada\Links\LinksServiceProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateTrackingLink
{
    use AsAction;

    public function handle(Affiliate $affiliate, string $destinationUrl, array $attributes = []): AffiliateLink
    {
        $affiliate = Affiliate::query()->whereKey($affiliate->getKey())->firstOrFail();

        if (! $affiliate->canBeAttributed()) {
            throw new AffiliateNotFoundException("Affiliate {$affiliate->code} cannot receive attribution.");
        }

        $programId = Arr::get($attributes, 'program_id');

        if (is_string($programId) || is_int($programId)) {
            if (! config('affiliates.owner.enabled', false)) {
                $program = AffiliateProgram::query()->findOrFail($programId);
            } else {
                $program = OwnerWriteGuard::findOrFailForOwner(
                    AffiliateProgram::class,
                    $programId,
                    includeGlobal: true,
                    message: 'Selected program is not accessible in the current owner scope.',
                );
            }

            $programId = (string) $program->getKey();
        }

        $params = Arr::get($attributes, 'params', []);

        if (! is_array($params)) {
            $params = [];
        }

        $subjectMetadata = Arr::get($attributes, 'subject_metadata');

        if (! is_array($subjectMetadata)) {
            $subjectMetadata = null;
        } else {
            $subjectMetadata = $this->sanitizeSubjectMetadata($subjectMetadata);
        }

        if (! class_exists(CreatePublicLink::class) || ! app()->providerIsLoaded(LinksServiceProvider::class)) {
            throw new LogicException('Install and enable aiarmada/links to create affiliate tracking links.');
        }

        $allowedHosts = AffiliateLinkDestination::allowedHosts($destinationUrl);

        Validator::make($attributes, [
            'tracking_url' => ['prohibited'],
            'short_url' => ['prohibited'],
            'custom_slug' => ['prohibited'],
            'ttl_seconds' => ['prohibited'],
            'link_style' => ['sometimes', 'string', 'in:short,branded'],
            'link_label' => ['nullable', 'string', 'max:60'],
        ])->validate();

        return DB::transaction(function () use ($affiliate, $destinationUrl, $attributes, $programId, $params, $subjectMetadata, $allowedHosts): AffiliateLink {
            $affiliateLink = AffiliateLink::query()->create([
                'affiliate_id' => $affiliate->getKey(),
                'program_id' => $programId,
                'destination_url' => $destinationUrl,
                'tracking_url' => '',
                'campaign' => Arr::get($attributes, 'campaign'),
                'sub_id' => Arr::get($attributes, 'sub_id'),
                'sub_id_2' => Arr::get($attributes, 'sub_id_2'),
                'sub_id_3' => Arr::get($attributes, 'sub_id_3'),
                'subject_type' => Arr::get($attributes, 'subject_type'),
                'subject_key' => Arr::get($attributes, 'subject_key'),
                'subject_id' => Arr::get($attributes, 'subject_id'),
                'subject_instance' => Arr::get($attributes, 'subject_instance'),
                'subject_title_snapshot' => Str::limit((string) Arr::get($attributes, 'subject_title_snapshot', ''), 200, ''),
                'subject_metadata' => $subjectMetadata,
                'origin' => Arr::get($attributes, 'origin'),
                'deactivated_at' => Arr::get($attributes, 'deactivated_at'),
            ]);

            $tracked = CreatePublicLink::run(
                [
                    'name' => $attributes['campaign'] ?? $affiliate->name,
                    'destination_url' => $destinationUrl,
                    'parameters' => array_merge($params, ['aff_link' => (string) $affiliateLink->getKey()]),
                    'subject_type' => $affiliateLink->getMorphClass(),
                    'subject_id' => (string) $affiliateLink->getKey(),
                    'expires_at' => $attributes['expires_at'] ?? null,
                    'max_clicks' => $attributes['max_clicks'] ?? null,
                ],
                $attributes['link_style'] ?? config('affiliates.links.default_style', 'short'),
                $affiliate->handle,
                $attributes['link_label'] ?? $attributes['campaign'] ?? 'link',
                false,
                $allowedHosts
            );

            $affiliateLink->update(['tracking_url' => GenerateLinkUrl::run($tracked)]);

            return $affiliateLink;
        });
    }

    /** @param array<string, mixed> $metadata */
    private function sanitizeSubjectMetadata(array $metadata): ?array
    {
        $promotedKeys = [
            'affiliate_id', 'affiliate_code', 'affiliate_attribution_id',
            'subject_type', 'subject_key', 'subject_id', 'subject_instance',
            'subject_title_snapshot', 'origin', 'voucher_code', 'program_id',
        ];

        $metadata = Arr::except($metadata, $promotedKeys);

        return $metadata === [] ? null : $metadata;
    }
}
