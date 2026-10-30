<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Identity;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateOwnershipCapability;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Issue a one-time ownership capability for an affiliate. While an active
 * capability exists, no replacement is issued: a lost capability is
 * permanently unverifiable.
 */
final class IssueOwnershipCapability
{
    use AsAction;

    /**
     * @param  array{ttl_days?: int, expires_at?: CarbonImmutable, label?: string}  $options
     * @return array{capability: AffiliateOwnershipCapability, plaintext_secret: string}
     */
    public function handle(Affiliate $affiliate, array $options = []): array
    {
        $now = CarbonImmutable::now();

        $activeExists = AffiliateOwnershipCapability::query()
            ->where('affiliate_id', $affiliate->getKey())
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->whereNull('consumed_at')
            ->where(static fn ($query): mixed => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->exists();

        if ($activeExists) {
            throw new InvalidArgumentException('An active ownership capability already exists for this affiliate.');
        }

        $secret = bin2hex(random_bytes(32));
        $expiresAt = $options['expires_at'] ?? $now->addDays((int) ($options['ttl_days'] ?? 30));

        $capability = AffiliateOwnershipCapability::query()->create([
            'affiliate_id' => $affiliate->getKey(),
            'owner_type' => $affiliate->owner_type,
            'owner_id' => $affiliate->owner_id,
            'verifier_hash' => hash('sha256', $secret),
            'status' => 'active',
            'label' => $options['label'] ?? null,
            'issued_at' => $now,
            'expires_at' => $expiresAt,
        ]);

        return ['capability' => $capability, 'plaintext_secret' => $secret];
    }
}
