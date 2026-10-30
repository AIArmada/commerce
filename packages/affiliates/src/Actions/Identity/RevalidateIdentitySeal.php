<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Identity;

use AIArmada\Affiliates\Exceptions\SealedIdentityException;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateClaim;
use AIArmada\Affiliates\Models\AffiliateCredential;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Write-time revalidation seam for mutating activity writers.
 *
 * Callers must hold the affiliate row lock first, then the credential row
 * lock, then call this seam in the same transaction immediately before
 * writing. It throws when any claim row — active or revoked — exists for
 * the affiliate, so in-flight activity racing a committing claim is sealed
 * instead of appending to sealed history. On failure the caller issues a
 * fresh identity.
 */
final class RevalidateIdentitySeal
{
    use AsAction;

    public function handle(Affiliate $lockedAffiliate, AffiliateCredential $credential): void
    {
        $sealed = AffiliateClaim::query()
            ->where('affiliate_id', $lockedAffiliate->getKey())
            ->exists();

        if ($lockedAffiliate->identity_sealed_at !== null || $sealed) {
            throw SealedIdentityException::forAffiliate((string) $lockedAffiliate->getKey());
        }
    }
}
