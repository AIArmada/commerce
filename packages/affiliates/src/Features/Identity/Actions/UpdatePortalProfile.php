<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Actions;

use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Features\Identity\Api\PortalActorAffiliateReader;
use AIArmada\Affiliates\Features\Identity\Api\PortalProfileAdministration;
use AIArmada\Affiliates\Features\Identity\Api\PortalProfileInput;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutMethodAdministration;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;

final readonly class UpdatePortalProfile implements PortalProfileAdministration
{
    public function __construct(
        private PayoutMethodAdministration $payoutMethods,
        private PortalActorAffiliateReader $actorAffiliates,
    ) {}

    public function save(string $affiliateId, string $actorType, string $actorId, PortalProfileInput $input): void
    {
        if ($actorType === '' || $actorId === '') {
            throw new AuthorizationException('An authenticated affiliate account is required.');
        }

        $validated = validator([
            'name' => $input->name,
            'contact_email' => $input->contactEmail,
            'website_url' => $input->websiteUrl,
            'payout_method_type' => $input->payoutMethodType,
            'payout_method_label' => $input->payoutMethodLabel,
            'payout_method_account_ref' => $input->payoutMethodAccountRef,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'payout_method_type' => ['required', 'string', Rule::in(array_column(PayoutMethodType::cases(), 'value'))],
            'payout_method_label' => ['required', 'string', 'max:255'],
            'payout_method_account_ref' => ['required', 'string', 'max:255'],
        ])->validate();

        $save = function () use ($affiliateId, $actorType, $actorId, $input, $validated): void {
            (new Affiliate)->getConnection()->transaction(function () use ($affiliateId, $actorType, $actorId, $input, $validated): void {
                $actorAffiliateId = $this->actorAffiliates->affiliateIdForActor($actorType, $actorId);

                if ($actorAffiliateId === null || $actorAffiliateId !== $affiliateId) {
                    throw new AuthorizationException('The affiliate account is not accessible.');
                }

                $affiliate = Affiliate::query()->forOwner()
                    ->whereKey($affiliateId)
                    ->lockForUpdate()
                    ->first();

                if ($affiliate === null) {
                    throw new AuthorizationException('The affiliate account is not accessible.');
                }

                $affiliate->update([
                    'handle' => config('affiliates.handles.allow_custom', true) ? $input->handle : $affiliate->handle,
                    'name' => $validated['name'],
                    'contact_email' => $validated['contact_email'] ?: null,
                    'website_url' => $validated['website_url'] ?: null,
                ]);

                $this->payoutMethods->setDefault(
                    $affiliateId,
                    $validated['payout_method_type'],
                    $validated['payout_method_label'],
                    $validated['payout_method_account_ref'],
                );
            }, attempts: 3);
        };

        $config = Affiliate::ownerScopeConfig();

        if ($config->enabled) {
            $owner = OwnerContext::resolveConfigured($config);
            OwnerContext::assertResolvedOrExplicitGlobal($owner);

            OwnerContext::withOwner($owner, $save);

            return;
        }

        $save();
    }
}
