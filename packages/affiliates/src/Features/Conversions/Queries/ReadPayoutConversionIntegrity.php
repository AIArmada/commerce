<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionIntegrity;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final readonly class ReadPayoutConversionIntegrity implements PayoutConversionIntegrity
{
    public function hasConversions(string $payoutId, int $connectionIdentity): bool
    {
        return $this->linked($payoutId, $connectionIdentity)->exists();
    }

    public function currencies(string $payoutId, int $connectionIdentity): array
    {
        return $this->linked($payoutId, $connectionIdentity)
            ->distinct()
            ->pluck('commission_currency')
            ->map(static fn (mixed $currency): string => mb_strtoupper((string) $currency))
            ->unique()
            ->values()
            ->all();
    }

    public function affiliateIds(string $payoutId, int $connectionIdentity, ?string $exceptConversionId = null): array
    {
        $query = $this->linked($payoutId, $connectionIdentity);

        if ($exceptConversionId !== null) {
            $query->whereKeyNot($exceptConversionId);
        }

        return $query->distinct()->pluck('affiliate_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /** @return Builder<AffiliateConversion> */
    private function linked(string $payoutId, int $connectionIdentity): Builder
    {
        if (spl_object_id((new AffiliateConversion)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Payout conversion integrity requires a shared database connection.');
        }

        return AffiliateConversion::query()->withoutOwnerScope()->where('affiliate_payout_id', $payoutId);
    }
}
