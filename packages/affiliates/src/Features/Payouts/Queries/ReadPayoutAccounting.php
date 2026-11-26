<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Queries;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutAccountingReader;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutAccountingRecord;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Features\Payouts\States\CancelledPayout;
use AIArmada\Affiliates\Features\Payouts\States\CompletedPayout;
use AIArmada\Affiliates\Features\Payouts\States\FailedPayout;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final readonly class ReadPayoutAccounting implements PayoutAccountingReader
{
    public function hasAffiliateHistory(string $affiliateId, int $connectionIdentity): bool
    {
        $this->assertConnection($connectionIdentity);
        app(AffiliateAccessGuard::class)->assertConnection($connectionIdentity);

        if (spl_object_id((new AffiliatePayoutOperation)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Payout history requires a shared database connection.');
        }

        if (! Str::isUuid($affiliateId)) {
            return false;
        }

        return AffiliatePayout::query()->withoutOwnerScope()
            ->where('payee_type', app(AffiliateProfileReader::class)->morphType())
            ->where('payee_id', $affiliateId)->exists()
            || AffiliatePayoutOperation::query()->withoutOwnerScope()->where('affiliate_id', $affiliateId)->exists();
    }

    public function isOpen(string $payoutId, int $connectionIdentity): bool
    {
        $this->assertConnection($connectionIdentity);

        if (! Str::isUuid($payoutId)) {
            return false;
        }

        return AffiliatePayout::query()
            ->withoutOwnerScope()
            ->whereKey($payoutId)
            ->whereNotIn('status', [CompletedPayout::value(), FailedPayout::value(), CancelledPayout::value()])
            ->exists();
    }

    public function assertConversionLink(
        string $payoutId,
        string $conversionId,
        ?string $existingConversionId,
        string $affiliateId,
        string $currency,
        int $connectionIdentity,
    ): void {
        $this->assertConnection($connectionIdentity);

        $payout = Str::isUuid($payoutId) ? AffiliatePayout::query()->forOwner()->find($payoutId) : null;

        if ($payout === null) {
            throw new InvalidArgumentException(sprintf(
                'Cannot link conversion [%s] to payout [%s]: the payout is missing or outside the owner scope.',
                $conversionId,
                $payoutId,
            ));
        }

        $payout->assertSingleAffiliateConversions($affiliateId, $existingConversionId);

        $conversionCurrency = mb_strtoupper($currency);
        $payoutCurrency = mb_strtoupper($payout->currency);

        if ($conversionCurrency !== '' && $conversionCurrency !== $payoutCurrency) {
            throw new InvalidArgumentException(sprintf(
                'Cannot link conversion [%s] (%s) to payout [%s] (%s): conversions must use the same currency as their payout.',
                $conversionId,
                $conversionCurrency,
                $payoutId,
                $payoutCurrency,
            ));
        }
    }

    public function locked(string $payoutId, int $connectionIdentity): PayoutAccountingRecord
    {
        $this->assertConnection($connectionIdentity);

        if ((new AffiliatePayout)->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Payout accounting requires a shared active database transaction.');
        }

        $payout = AffiliatePayout::query()->forOwner()->lockForUpdate()->findOrFail($payoutId);

        return new PayoutAccountingRecord(
            (string) $payout->getKey(),
            $payout->status->getValue(),
            $payout->owner_type,
            $payout->owner_id,
            $payout->affiliate_payout_operation_id,
            (int) $payout->conversion_count,
        );
    }

    private function assertConnection(int $connectionIdentity): void
    {
        if (spl_object_id((new AffiliatePayout)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Payout accounting requires a shared database connection.');
        }
    }
}
