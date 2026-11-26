<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Actions;

use AIArmada\Affiliates\Exceptions\SealedIdentityException;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Identity\Models\AffiliateCredential;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Issue a reusable activity credential for an affiliate. Reuse never
 * reissues ownership proof: claiming history requires a separate
 * ownership capability.
 */
final class IssueActivityCredential
{
    use AsAction;

    /**
     * @param  array{ttl_days?: int, expires_at?: CarbonImmutable, label?: string}  $options
     * @return array{credential: AffiliateCredential, plaintext_secret: string}
     */
    public function handle(Affiliate $affiliate, array $options = []): array
    {
        return $affiliate->getConnection()->transaction(function () use ($affiliate, $options): array {
            $affiliate = Affiliate::query()->whereKey($affiliate->getKey())->lockForUpdate()->firstOrFail();
            if ($affiliate->identity_sealed_at !== null) {
                throw SealedIdentityException::forAffiliate((string) $affiliate->getKey());
            }

            $secret = bin2hex(random_bytes(32));
            $expiresAt = $options['expires_at'] ?? CarbonImmutable::now()->addDays((int) ($options['ttl_days'] ?? 30));

            $credential = AffiliateCredential::query()->create([
                'affiliate_id' => $affiliate->getKey(),
                'owner_type' => $affiliate->owner_type,
                'owner_id' => $affiliate->owner_id,
                'verifier_hash' => hash('sha256', $secret),
                'status' => 'active',
                'label' => $options['label'] ?? null,
                'issued_at' => CarbonImmutable::now(),
                'expires_at' => $expiresAt,
            ]);

            return ['credential' => $credential, 'plaintext_secret' => $secret];
        });
    }
}
