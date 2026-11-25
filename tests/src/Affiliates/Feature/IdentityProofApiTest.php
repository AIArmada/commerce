<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Identity\Api\IdentityProofApi;
use AIArmada\Affiliates\Features\Identity\Api\IssuedIdentityProof;
use AIArmada\Affiliates\Features\Identity\Api\IdentityClaimRecord;
use AIArmada\Affiliates\Features\Identity\Models\AffiliateCredential;
use AIArmada\Affiliates\Exceptions\SealedIdentityException;
use AIArmada\CommerceSupport\Data\RecordReference;

it('exposes immutable proof records and refuses issuance after sealing', function (): void {
    $affiliate = createTestAffiliate(['code' => 'PROOF-API', 'purpose' => 'host_internal_only']);
    $api = app(IdentityProofApi::class);
    $id = (string) $affiliate->getKey();

    $credential = $api->issueActivityCredential($id);
    $capability = $api->issueOwnershipCapability($id);
    expect($credential)->toBeInstanceOf(IssuedIdentityProof::class)
        ->and(AffiliateCredential::query()->findOrFail($credential->id)->verifier_hash)->toBe(hash('sha256', $credential->plaintextSecret));

    $claim = $api->claim($capability->plaintextSecret, new RecordReference('user', 'proof-test'), true);
    expect($claim)->toBeInstanceOf(IdentityClaimRecord::class)
        ->and($claim->affiliateId)->toBe($id);

    expect(fn () => $api->issueActivityCredential($id))->toThrow(SealedIdentityException::class);
    expect(fn () => $api->issueOwnershipCapability($id))->toThrow(SealedIdentityException::class);
});

it('rejects activity credentials issued to another affiliate', function (): void {
    $a = createTestAffiliate(['code' => 'PROOF-A', 'purpose' => 'host_internal_only']);
    $b = createTestAffiliate(['code' => 'PROOF-B', 'purpose' => 'host_internal_only']);
    $api = app(IdentityProofApi::class);
    $credential = $api->issueActivityCredential((string) $b->getKey());

    expect(fn () => $a->getConnection()->transaction(fn () => $api->assertActivityWritable((string) $a->getKey(), $credential->id)))
        ->toThrow(InvalidArgumentException::class, 'invalid for this affiliate');
});
