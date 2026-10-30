<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Identity\ClaimAffiliateIdentity;
use AIArmada\Affiliates\Actions\Identity\IssueActivityCredential;
use AIArmada\Affiliates\Actions\Identity\IssueOwnershipCapability;
use AIArmada\Affiliates\Actions\Identity\RevalidateIdentitySeal;
use AIArmada\Affiliates\Exceptions\SealedIdentityException;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateClaim;
use AIArmada\Affiliates\Models\AffiliateCredential;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EngineRace;

function raceIdentityAffiliate(string $code): Affiliate
{
    return Affiliate::query()->create([
        'code' => $code,
        'name' => 'Race Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 0,
        'currency' => 'USD',
        'purpose' => 'host_internal_only',
    ]);
}

function raceIdentityClaimant(string $email): array
{
    $user = User::query()->create([
        'name' => 'Race Claimant',
        'email' => $email,
        'password' => 'secret',
    ]);

    return [$user->getMorphClass(), (string) $user->getKey()];
}

test('concurrent claims on one capability seal to a single claim row', function (): void {
    $this->useEngine();

    $affiliate = raceIdentityAffiliate('RACE-CLAIM-' . Str::upper(Str::random(8)));
    [$typeA, $idA] = raceIdentityClaimant('race-a-' . uniqid() . '@example.com');
    [$typeB, $idB] = raceIdentityClaimant('race-b-' . uniqid() . '@example.com');

    $capability = app(IssueOwnershipCapability::class)->run($affiliate);
    $verifier = $capability['plaintext_secret'];
    $affiliateId = (string) $affiliate->getKey();

    $results = EngineRace::run([
        'claimer_a' => fn (): array => claimerAttempt($verifier, $typeA, $idA),
        'claimer_b' => fn (): array => claimerAttempt($verifier, $typeB, $idB),
    ]);

    $claimed = collect($results)->where('claimed', true)->count();

    expect($claimed)->toBe(1)
        ->and(AffiliateClaim::query()->where('affiliate_id', $affiliateId)->count())->toBe(1);
});

function claimerAttempt(string $verifier, string $type, string $id): array
{
    try {
        app(ClaimAffiliateIdentity::class)->run($verifier, $type, $id, true);

        return ['claimed' => true, 'sealed' => false];
    } catch (SealedIdentityException) {
        return ['claimed' => false, 'sealed' => true];
    }
}

test('activity write racing a committing claim is sealed at revalidation', function (): void {
    $this->useEngine();

    $affiliate = raceIdentityAffiliate('RACE-SEAL-' . Str::upper(Str::random(8)));
    [$type, $id] = raceIdentityClaimant('race-seal-' . uniqid() . '@example.com');

    $issued = app(IssueActivityCredential::class)->run($affiliate);
    $capability = app(IssueOwnershipCapability::class)->run($affiliate);
    $verifier = $capability['plaintext_secret'];
    $affiliateId = (string) $affiliate->getKey();
    $credentialId = (string) $issued['credential']->getKey();

    $results = EngineRace::run([
        'claimer' => function () use ($verifier, $type, $id): array {
            app(ClaimAffiliateIdentity::class)->run($verifier, $type, $id, true);

            return ['claimed' => true];
        },
        'writer' => function () use ($affiliateId, $credentialId): array {
            sleep(3);

            try {
                DB::transaction(function () use ($affiliateId, $credentialId): void {
                    $lockedAffiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);
                    $credential = AffiliateCredential::query()->lockForUpdate()->findOrFail($credentialId);

                    app(RevalidateIdentitySeal::class)->run($lockedAffiliate, $credential);
                });

                return ['sealed' => false];
            } catch (SealedIdentityException) {
                return ['sealed' => true];
            }
        },
    ]);

    expect($results['claimer'])->toBe(['claimed' => true])
        ->and($results['writer'])->toBe(['sealed' => true]);
});
