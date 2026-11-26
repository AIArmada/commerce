<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionHistoryReader;
use AIArmada\Affiliates\Features\Conversions\Api\UnusedAffiliateBalanceRemoval;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateBalance;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class RemoveUnusedAffiliateBalances implements UnusedAffiliateBalanceRemoval
{
    public function __construct(private ConversionHistoryReader $history) {}

    public function remove(string $affiliateId): void
    {
        $connection = (new AffiliateBalance)->getConnection();
        $guard = app(AffiliateAccessGuard::class);
        $identity = spl_object_id($connection);
        $guard->assertConnection($identity);

        if ($connection !== (new AffiliateConversion)->getConnection()) {
            throw new LogicException('Affiliate balance cleanup requires a shared database connection.');
        }

        $connection->transaction(function () use ($affiliateId, $guard, $identity): void {
            $guard->lockForWrite($affiliateId, $identity);

            if ($this->history->hasAffiliateHistory($affiliateId, $identity)) {
                throw ValidationException::withMessages(['affiliate' => 'An affiliate with conversion history cannot be deleted.']);
            }

            $balances = AffiliateBalance::query()->where('affiliate_id', $affiliateId)->lockForUpdate()->get();

            foreach ($balances as $balance) {
                if ($balance->holding_minor !== 0 || $balance->available_minor !== 0
                    || $balance->lifetime_earnings_minor !== 0 || $balance->payout_sequence !== 0) {
                    throw ValidationException::withMessages(['affiliate' => 'An affiliate with financial balances or payout history cannot be deleted.']);
                }
            }

            AffiliateBalance::query()->where('affiliate_id', $affiliateId)->delete();
        }, attempts: 3);
    }
}
