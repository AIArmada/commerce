<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Identity;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCredential;
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
    }
}
