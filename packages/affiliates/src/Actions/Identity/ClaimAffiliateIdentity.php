<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Identity;

use AIArmada\Affiliates\Exceptions\SealedIdentityException;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateClaim;
use AIArmada\Affiliates\Models\AffiliateCredential;
use AIArmada\Affiliates\Models\AffiliateOwnershipCapability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Claim an affiliate identity by presenting the one-time ownership
 * capability. The caller asserts authorization (consent wording and
 * collection stay host-side); a false assertion throws before any
 * proof is processed.
 *
 * Exactly one claim row ever exists per affiliate. A replay by the same
 * claimant returns the active claim; any other claimant — and any replay
 * after revocation — meets the permanent seal.
 */
final class ClaimAffiliateIdentity
{
    use AsAction;

    public function handle(
        string $capabilityVerifier,
        string $claimantType,
        string | int $claimantId,
        bool $authorizationAssertion,
    ): AffiliateClaim {
        if (! $authorizationAssertion) {
            throw new InvalidArgumentException('Claim requires an explicit caller authorization assertion.');
        }

        return DB::transaction(function () use ($capabilityVerifier, $claimantType, $claimantId): AffiliateClaim {
            $capability = AffiliateOwnershipCapability::query()
                ->where('verifier_hash', hash('sha256', $capabilityVerifier))
                ->lockForUpdate()
                ->first();

            if (! $capability instanceof AffiliateOwnershipCapability) {
                throw new InvalidArgumentException('Ownership capability is invalid.');
            }

            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($capability->affiliate_id);

            $existing = AffiliateClaim::query()
                ->where('affiliate_id', $affiliate->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing instanceof AffiliateClaim) {
                if ($existing->isActive() && $existing->belongsToClaimant($claimantType, $claimantId)) {
                    return $existing;
                }

                throw SealedIdentityException::forAffiliate((string) $affiliate->getKey());
            }

            $now = CarbonImmutable::now();

            if ($capability->revoked_at !== null || $capability->status !== 'active') {
                throw new InvalidArgumentException('Ownership capability is revoked.');
            }

            if ($capability->consumed_at !== null) {
                throw new InvalidArgumentException('Ownership capability is already consumed.');
            }

            if ($capability->expires_at !== null && ! $now->lessThan($capability->expires_at)) {
                throw new InvalidArgumentException('Ownership capability is expired.');
            }

            // Updating the locked row makes stale PostgreSQL snapshots fail
            // with a serialization error before activity can acquire its lock.
            $affiliate->forceFill(['identity_sealed_at' => $now])->save();

            $claim = AffiliateClaim::query()->create([
                'affiliate_id' => $affiliate->getKey(),
                'owner_type' => $affiliate->owner_type,
                'owner_id' => $affiliate->owner_id,
                'claimant_type' => $claimantType,
                'claimant_id' => (string) $claimantId,
                'capability_verifier_hash' => $capability->verifier_hash,
                'status' => 'active',
                'claimed_at' => $now,
            ]);

            $capability->forceFill(['status' => 'consumed', 'consumed_at' => $now])->save();

            AffiliateCredential::query()
                ->where('affiliate_id', $affiliate->getKey())
                ->where('status', 'active')
                ->whereNull('revoked_at')
                ->update(['status' => 'revoked', 'revoked_at' => $now]);

            return $claim;
        });
    }
}
