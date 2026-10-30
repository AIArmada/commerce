<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Identity\ClaimAffiliateIdentity;
use AIArmada\Affiliates\Actions\Identity\IssueActivityCredential;
use AIArmada\Affiliates\Actions\Identity\IssueOwnershipCapability;
use AIArmada\Affiliates\Actions\Identity\RevalidateIdentitySeal;
use AIArmada\Affiliates\Actions\Identity\RevokeAffiliateClaim;
use AIArmada\Affiliates\Exceptions\SealedIdentityException;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateClaim;
use AIArmada\Affiliates\Models\AffiliateCredential;
use AIArmada\Affiliates\Models\AffiliateOwnershipCapability;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

function identityTestAffiliate(string $code): Affiliate
{
    return createTestAffiliate(['code' => $code, 'purpose' => 'host_internal_only']);
}

/** @return array{0: string, 1: string} */
function identityTestClaimant(): array
{
    $user = User::query()->create([
        'name' => 'Identity Claimant',
        'email' => 'identity-claimant-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    return [$user->getMorphClass(), (string) $user->getKey()];
}

test('activity credential issuance stores a hashed verifier with expiry', function (): void {
    $affiliate = identityTestAffiliate('IDENT-CRED');

    $issued = app(IssueActivityCredential::class)->run($affiliate, ['ttl_days' => 30]);

    expect($issued['credential'])->toBeInstanceOf(AffiliateCredential::class)
        ->and($issued['plaintext_secret'])->toBeString()
        ->and($issued['credential']->verifier_hash)->not->toBe($issued['plaintext_secret'])
        ->and($issued['credential']->verifier_hash)->toBe(hash('sha256', $issued['plaintext_secret']))
        ->and($issued['credential']->expires_at)->not->toBeNull();

    $again = app(IssueActivityCredential::class)->run($affiliate);

    expect($again['credential']->getKey())->not->toBe($issued['credential']->getKey())
        ->and($again['plaintext_secret'])->not->toBe($issued['plaintext_secret']);
});

test('ownership capability reuse never reissues proof while active', function (): void {
    $affiliate = identityTestAffiliate('IDENT-CAP');

    app(IssueOwnershipCapability::class)->run($affiliate);

    expect(fn () => app(IssueOwnershipCapability::class)->run($affiliate))
        ->toThrow(InvalidArgumentException::class, 'active ownership capability');
});

test('claim with verifier and caller assertion creates a single active claim', function (): void {
    $affiliate = identityTestAffiliate('IDENT-CLAIM');
    [$type, $id] = identityTestClaimant();

    $capability = app(IssueOwnershipCapability::class)->run($affiliate);

    $claim = app(ClaimAffiliateIdentity::class)->run(
        $capability['plaintext_secret'],
        $type,
        $id,
        true,
    );

    expect($claim)->toBeInstanceOf(AffiliateClaim::class)
        ->and($claim->status)->toBe('active')
        ->and((string) $claim->affiliate_id)->toBe((string) $affiliate->getKey())
        ->and($claim->claimant_type)->toBe($type)
        ->and((string) $claim->claimant_id)->toBe((string) $id);
});

test('false caller assertion throws before any proof is processed', function (): void {
    $affiliate = identityTestAffiliate('IDENT-NOASSERT');
    [$type, $id] = identityTestClaimant();

    $capability = app(IssueOwnershipCapability::class)->run($affiliate);

    expect(fn () => app(ClaimAffiliateIdentity::class)->run(
        $capability['plaintext_secret'],
        $type,
        $id,
        false,
    ))->toThrow(InvalidArgumentException::class, 'authorization');

    expect(AffiliateClaim::query()->where('affiliate_id', $affiliate->getKey())->count())->toBe(0);
});

test('same claimant re-claim is idempotent while another claimant is sealed', function (): void {
    $affiliate = identityTestAffiliate('IDENT-IDEM');
    [$type, $id] = identityTestClaimant();
    [$otherType, $otherId] = identityTestClaimant();

    $capability = app(IssueOwnershipCapability::class)->run($affiliate);
    $first = app(ClaimAffiliateIdentity::class)->run($capability['plaintext_secret'], $type, $id, true);

    $replay = app(ClaimAffiliateIdentity::class)->run($capability['plaintext_secret'], $type, $id, true);

    expect($replay->getKey())->toBe($first->getKey())
        ->and(AffiliateClaim::query()->where('affiliate_id', $affiliate->getKey())->count())->toBe(1);

    expect(fn () => app(ClaimAffiliateIdentity::class)->run($capability['plaintext_secret'], $otherType, $otherId, true))
        ->toThrow(SealedIdentityException::class);
});

test('revoke preserves the row and blocks re-claim even by the same claimant', function (): void {
    $affiliate = identityTestAffiliate('IDENT-REVOKE');
    [$type, $id] = identityTestClaimant();

    $capability = app(IssueOwnershipCapability::class)->run($affiliate);
    $claim = app(ClaimAffiliateIdentity::class)->run($capability['plaintext_secret'], $type, $id, true);

    app(RevokeAffiliateClaim::class)->run($claim->refresh());

    $stored = AffiliateClaim::query()->whereKey($claim->getKey())->firstOrFail();

    expect($stored->status)->toBe('revoked')
        ->and($stored->revoked_at)->not->toBeNull();

    expect(fn () => app(ClaimAffiliateIdentity::class)->run($capability['plaintext_secret'], $type, $id, true))
        ->toThrow(SealedIdentityException::class);
});

test('seal covers the claimed pair while a fresh identity stays usable', function (): void {
    $affiliate = identityTestAffiliate('IDENT-SEAL');
    [$type, $id] = identityTestClaimant();

    $issued = app(IssueActivityCredential::class)->run($affiliate);
    $capability = app(IssueOwnershipCapability::class)->run($affiliate);
    app(ClaimAffiliateIdentity::class)->run($capability['plaintext_secret'], $type, $id, true);

    expect(fn () => DB::transaction(function () use ($affiliate, $issued): void {
        $locked = Affiliate::query()->lockForUpdate()->findOrFail($affiliate->getKey());
        $credential = AffiliateCredential::query()->lockForUpdate()->findOrFail($issued['credential']->getKey());

        app(RevalidateIdentitySeal::class)->run($locked, $credential);
    }))->toThrow(SealedIdentityException::class);

    $fresh = identityTestAffiliate('IDENT-SEAL-FRESH');
    $freshIssued = app(IssueActivityCredential::class)->run($fresh);

    DB::transaction(function () use ($fresh, $freshIssued): void {
        $locked = Affiliate::query()->lockForUpdate()->findOrFail($fresh->getKey());
        $credential = AffiliateCredential::query()->lockForUpdate()->findOrFail($freshIssued['credential']->getKey());

        app(RevalidateIdentitySeal::class)->run($locked, $credential);
    });

    expect(true)->toBeTrue();
});

test('expired and consumed capabilities are rejected without sealing', function (): void {
    $affiliate = identityTestAffiliate('IDENT-EXP');
    [$type, $id] = identityTestClaimant();

    $expired = app(IssueOwnershipCapability::class)->run($affiliate, [
        'expires_at' => CarbonImmutable::now()->subMinute(),
    ]);

    expect(fn () => app(ClaimAffiliateIdentity::class)->run($expired['plaintext_secret'], $type, $id, true))
        ->toThrow(InvalidArgumentException::class, 'expired');

    $affiliate2 = identityTestAffiliate('IDENT-EXP-2');
    $capability = app(IssueOwnershipCapability::class)->run($affiliate2);
    app(ClaimAffiliateIdentity::class)->run($capability['plaintext_secret'], $type, $id, true);

    expect(AffiliateOwnershipCapability::query()->where('affiliate_id', $affiliate2->getKey())->firstOrFail()->consumed_at)
        ->not->toBeNull();
});

test('the affiliate identity seal is permanent after claim revocation', function (): void {
    $affiliate = identityTestAffiliate('IDENT-PERMANENT-SEAL');
    [$type, $id] = identityTestClaimant();
    $capability = app(IssueOwnershipCapability::class)->run($affiliate);
    $claim = app(ClaimAffiliateIdentity::class)->run($capability['plaintext_secret'], $type, $id, true);
    $seal = $affiliate->refresh()->identity_sealed_at;

    expect($seal)->toBeInstanceOf(CarbonImmutable::class)
        ->and($seal->toDateTimeString())->toBe($claim->claimed_at->toDateTimeString());

    app(RevokeAffiliateClaim::class)->run($claim);

    expect($affiliate->refresh()->identity_sealed_at->equalTo($seal))->toBeTrue();
    expect(fn () => $affiliate->forceFill(['identity_sealed_at' => null])->save())
        ->toThrow(LogicException::class, 'identity seal is permanent');
});
