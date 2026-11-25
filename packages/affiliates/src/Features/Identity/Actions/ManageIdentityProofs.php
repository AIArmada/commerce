<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Actions;

use AIArmada\Affiliates\Features\Identity\Api\IdentityProofApi;
use AIArmada\Affiliates\Features\Identity\Api\IssuedIdentityProof;
use AIArmada\Affiliates\Features\Identity\Api\IdentityClaimRecord;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Features\Identity\Models\AffiliateClaim;
use AIArmada\Affiliates\Features\Identity\Models\AffiliateCredential;
use AIArmada\CommerceSupport\Data\RecordReference;
use Carbon\CarbonImmutable;
use LogicException;
use SensitiveParameter;

final readonly class ManageIdentityProofs implements IdentityProofApi
{
    public function __construct(
        private IssueActivityCredential $activity,
        private IssueOwnershipCapability $ownership,
        private ClaimAffiliateIdentity $claimIdentity,
        private RevokeAffiliateClaim $revokeClaim,
        private RevalidateIdentitySeal $validate,
    ) {}

    public function issueActivityCredential(string $affiliateId, ?CarbonImmutable $expiresAt = null, ?string $label = null): IssuedIdentityProof
    {
        $affiliate = Affiliate::query()->findOrFail($affiliateId);
        $issued = $this->activity->handle($affiliate, array_filter(['expires_at' => $expiresAt, 'label' => $label], static fn (mixed $value): bool => $value !== null));
        return new IssuedIdentityProof((string) $issued['credential']->getKey(), $issued['plaintext_secret'], $issued['credential']->expires_at === null ? null : CarbonImmutable::instance($issued['credential']->expires_at));
    }

    public function issueOwnershipCapability(string $affiliateId, ?CarbonImmutable $expiresAt = null, ?string $label = null): IssuedIdentityProof
    {
        $affiliate = Affiliate::query()->findOrFail($affiliateId);
        $issued = $this->ownership->handle($affiliate, array_filter(['expires_at' => $expiresAt, 'label' => $label], static fn (mixed $value): bool => $value !== null));
        return new IssuedIdentityProof((string) $issued['capability']->getKey(), $issued['plaintext_secret'], $issued['capability']->expires_at === null ? null : CarbonImmutable::instance($issued['capability']->expires_at));
    }

    public function claim(#[SensitiveParameter] string $verifier, RecordReference $claimant, bool $authorizationAssertion): IdentityClaimRecord
    {
        $claim = $this->claimIdentity->handle($verifier, $claimant->type, $claimant->id, $authorizationAssertion);
        return new IdentityClaimRecord((string) $claim->getKey(), $claim->affiliate_id, new RecordReference($claim->claimant_type, $claim->claimant_id), $claim->status, $claim->claimed_at === null ? null : CarbonImmutable::instance($claim->claimed_at));
    }

    public function revoke(string $claimId): void
    {
        $this->revokeClaim->handle(AffiliateClaim::query()->findOrFail($claimId));
    }

    public function assertActivityWritable(string $affiliateId, string $credentialId): void
    {
        if ((new Affiliate)->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Activity revalidation requires an active affiliate transaction.');
        }

        $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);
        $credential = AffiliateCredential::query()->lockForUpdate()->findOrFail($credentialId);
        $this->validate->handle($affiliate, $credential);
    }
}
