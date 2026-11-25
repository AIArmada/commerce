<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\UnusedAffiliateBalanceRemoval;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class RemoveUnusedAffiliateBalances implements UnusedAffiliateBalanceRemoval
{
    public function remove(string $affiliateId): void
    {
        $connection = (new Affiliate)->getConnection();

        if ($connection !== (new AffiliateBalance)->getConnection()
            || $connection !== (new AffiliateConversion)->getConnection()) {
            throw new LogicException('Affiliate balance cleanup requires a shared database connection.');
        }

        $connection->transaction(function () use ($affiliateId): void {
            $affiliate = Affiliate::query()->forOwner()->lockForUpdate()->findOrFail($affiliateId);

            if ($affiliate->conversions()->exists()) {
                throw ValidationException::withMessages(['affiliate' => 'An affiliate with conversion history cannot be deleted.']);
            }

            $balances = $affiliate->balances()->lockForUpdate()->get();

            foreach ($balances as $balance) {
                if ($balance->holding_minor !== 0 || $balance->available_minor !== 0
                    || $balance->lifetime_earnings_minor !== 0 || $balance->payout_sequence !== 0) {
                    throw ValidationException::withMessages(['affiliate' => 'An affiliate with financial balances or payout history cannot be deleted.']);
                }
            }

            $affiliate->balances()->delete();
        }, attempts: 3);
    }
}
