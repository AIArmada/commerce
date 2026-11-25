<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

use Carbon\CarbonImmutable;
use AIArmada\CommerceSupport\Data\RecordReference;
use SensitiveParameter;

interface IdentityProofApi
{
    public function issueActivityCredential(string $affiliateId, ?CarbonImmutable $expiresAt = null, ?string $label = null): IssuedIdentityProof;

    public function issueOwnershipCapability(string $affiliateId, ?CarbonImmutable $expiresAt = null, ?string $label = null): IssuedIdentityProof;

    public function claim(#[SensitiveParameter] string $verifier, RecordReference $claimant, bool $authorizationAssertion): IdentityClaimRecord;

    public function revoke(string $claimId): void;

    public function assertActivityWritable(string $affiliateId, string $credentialId): void;
}
