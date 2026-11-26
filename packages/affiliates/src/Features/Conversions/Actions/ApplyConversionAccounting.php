<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Events\HoldingShortfallDetected;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateBalance;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Affiliates\Features\Conversions\States\ConversionStatus;
use AIArmada\Affiliates\Features\Conversions\States\PaidConversion;
use AIArmada\Affiliates\Features\Conversions\States\PendingConversion;
use AIArmada\Affiliates\Features\Conversions\States\QualifiedConversion;
use AIArmada\Affiliates\Features\Conversions\States\RejectedConversion;
use AIArmada\Affiliates\Features\Conversions\States\ReversedConversion;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Support\PayoutMinimums;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use LogicException;

final readonly class ApplyConversionAccounting
{
    public function handle(AffiliateConversion $conversion, ?ConversionStatus $previousStatus = null): void
    {
        $affiliate = $conversion->affiliate()->first();

        if (! $affiliate) {
            return;
        }

        $connection = $conversion->getConnection();

        if ($connection !== $affiliate->getConnection() || $connection !== (new AffiliateBalance)->getConnection()) {
            throw new LogicException('Conversion accounting requires a shared database connection.');
        }

        $connection->transaction(function () use ($affiliate, $conversion, $previousStatus): void {
            // Self-sufficient lock: callers hold this row already, but the
            // held read-modify-write below must be correct regardless.
            $lockedConversion = AffiliateConversion::query()
                ->whereKey($conversion->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAffiliate = Affiliate::query()
                ->whereKey($affiliate->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currency = mb_strtoupper((string) ($lockedConversion->commission_currency ?: $lockedAffiliate->currency ?: config('affiliates.currency.default', 'MYR')));

            $balance = AffiliateBalance::query()->where('affiliate_id', $lockedAffiliate->getKey())->where('currency', $currency)->lockForUpdate()->first()
                ?? self::createBalance($lockedAffiliate, $currency);

            if ($previousStatus === null) {
                $this->applyCreationAccounting($lockedConversion, $balance);
            } else {
                $this->applyTransitionAccounting($lockedConversion, $balance, $previousStatus);
            }
        });
    }

    private function addToHolding(AffiliateBalance $balance, int $amountMinor): void
    {
        $balance->increment('holding_minor', $amountMinor);
        $balance->increment('lifetime_earnings_minor', $amountMinor);
    }

    private function releaseFromHolding(AffiliateBalance $balance, int $amountMinor): void
    {
        $releaseAmount = min(max(0, $amountMinor), max(0, $balance->holding_minor));
        $balance->decrement('holding_minor', $releaseAmount);
        $balance->increment('available_minor', $releaseAmount);
    }

    private function deductFromAvailable(AffiliateBalance $balance, int $amountMinor): void
    {
        $deductAmount = min(max(0, $amountMinor), max(0, $balance->available_minor));
        $balance->decrement('available_minor', $deductAmount);
    }

    private function voidFromHolding(AffiliateBalance $balance, int $amountMinor): void
    {
        $voidAmount = min(max(0, $amountMinor), max(0, $balance->holding_minor));
        $balance->decrement('holding_minor', $voidAmount);
        $balance->decrement('lifetime_earnings_minor', min(max(0, $amountMinor), max(0, $balance->lifetime_earnings_minor)));
    }

    private function voidFromAvailable(AffiliateBalance $balance, int $amountMinor): void
    {
        $voidAmount = min(max(0, $amountMinor), max(0, $balance->available_minor));
        $balance->decrement('available_minor', $voidAmount);
        $balance->decrement('lifetime_earnings_minor', min(max(0, $amountMinor), max(0, $balance->lifetime_earnings_minor)));
    }

    private function clawbackFromAvailable(AffiliateBalance $balance, int $amountMinor): void
    {
        $amountMinor = max(0, $amountMinor);

        // Unlike deductFromAvailable, a clawback is an exact debit: the
        // money already left via payout, so recovery drives available
        // negative against future earnings instead of flooring at zero.
        // Lifetime floors at zero — recognition cannot un-count more
        // than was counted; the floor signals pre-existing inconsistency.
        $balance->decrement('available_minor', $amountMinor);
        $balance->decrement('lifetime_earnings_minor', min($amountMinor, max(0, $balance->lifetime_earnings_minor)));
    }

    private function applyCreationAccounting(AffiliateConversion $conversion, AffiliateBalance $balance): void
    {
        $status = $this->resolveStatus($conversion);

        if ($status->equals(ApprovedConversion::class)) {
            $balance->increment('available_minor', $conversion->commission_minor);
            $balance->increment('lifetime_earnings_minor', $conversion->commission_minor);

            return;
        }

        if ($status->equals(PendingConversion::class) || $status->equals(QualifiedConversion::class)) {
            $this->addToHolding($balance, $conversion->commission_minor);
            $conversion->forceFill(['held_minor' => (int) $conversion->commission_minor])->save();

            return;
        }

        if ($status->equals(PaidConversion::class)) {
            $balance->increment('lifetime_earnings_minor', $conversion->commission_minor);
        }
    }

    private function applyTransitionAccounting(AffiliateConversion $conversion, AffiliateBalance $balance, ConversionStatus $previousStatus): void
    {
        $newStatus = $this->resolveStatus($conversion);

        if ($previousStatus->equals(QualifiedConversion::class) || $previousStatus->equals(PendingConversion::class)) {
            if ($newStatus->equals(ApprovedConversion::class)) {
                // Per-conversion hold: the balance total may hold other
                // conversions' money. Release only what this conversion
                // recorded; void stale hold left by a downward
                // adjustment. The holding mutators clamp to the actual
                // pool, so every residual below is derived from the
                // APPLIED amount, never the requested one — the
                // affiliate always receives exactly commission_minor in
                // available, even against a divergent legacy pool.
                $heldMinor = (int) $conversion->held_minor;
                $commissionMinor = (int) $conversion->commission_minor;
                $releaseMinor = min($heldMinor, $commissionMinor);

                $holdingBeforeRelease = (int) $balance->holding_minor;
                $this->releaseFromHolding($balance, $releaseMinor);
                $releasedActual = $holdingBeforeRelease - (int) $balance->holding_minor;

                if ($releasedActual < $releaseMinor) {
                    $this->reportHoldingShortfall($conversion, 'release', $releaseMinor, $releasedActual);
                }

                $excessMinor = $heldMinor - $releaseMinor;

                if ($excessMinor > 0) {
                    $holdingBeforeVoid = (int) $balance->holding_minor;
                    $this->voidFromHolding($balance, $excessMinor);
                    $voidedActual = $holdingBeforeVoid - (int) $balance->holding_minor;

                    if ($voidedActual < $excessMinor) {
                        $this->reportHoldingShortfall($conversion, 'void', $excessMinor, $voidedActual);
                    }
                }

                $remainingMinor = $commissionMinor - $releasedActual;

                if ($remainingMinor > 0) {
                    $balance->increment('available_minor', $remainingMinor);
                }

                // Lifetime already counted the recorded hold at creation
                // (addToHolding), so only the never-recorded portion —
                // off-record remainder or upward adjustment — accrues.
                $lifetimeCatchUp = $commissionMinor - $heldMinor;

                if ($lifetimeCatchUp > 0) {
                    $balance->increment('lifetime_earnings_minor', $lifetimeCatchUp);
                }

                $conversion->forceFill(['held_minor' => 0])->save();
            }

            if ($newStatus->equals(RejectedConversion::class)) {
                $this->voidRecordedHold($conversion, $balance);
            }

            if ($newStatus->equals(ReversedConversion::class)) {
                $this->voidRecordedHold($conversion, $balance);
            }
        }

        if ($previousStatus->equals(ApprovedConversion::class) && $newStatus->equals(RejectedConversion::class)) {
            $this->voidFromAvailable($balance, $conversion->commission_minor);
        }

        if ($previousStatus->equals(ApprovedConversion::class) && $newStatus->equals(ReversedConversion::class)) {
            $this->voidFromAvailable($balance, $conversion->commission_minor);
        }

        if ($previousStatus->equals(PaidConversion::class) && $newStatus->equals(ReversedConversion::class)) {
            $this->clawbackFromAvailable($balance, $conversion->commission_minor);
        }

        if ($newStatus->equals(PaidConversion::class) && $conversion->affiliate_payout_id === null) {
            $this->deductFromAvailable($balance, $conversion->commission_minor);
        }
    }

    private function voidRecordedHold(AffiliateConversion $conversion, AffiliateBalance $balance): void
    {
        // Lifetime un-counting rides inside voidFromHolding and does not
        // depend on the pool state; only the holding move can clamp.
        $heldMinor = (int) $conversion->held_minor;
        $holdingBefore = (int) $balance->holding_minor;
        $this->voidFromHolding($balance, $heldMinor);
        $voidedActual = $holdingBefore - (int) $balance->holding_minor;

        if ($voidedActual < $heldMinor) {
            $this->reportHoldingShortfall($conversion, 'void', $heldMinor, $voidedActual);
        }

        $conversion->forceFill(['held_minor' => 0])->save();
    }

    private function reportHoldingShortfall(AffiliateConversion $conversion, string $operation, int $requestedMinor, int $appliedMinor): void
    {
        // The caller already made the affiliate whole from the applied
        // amount, so a shortfall is a ledger-hygiene signal — loud, but
        // never a reason to block the transition.
        Log::warning('affiliate.holding_shortfall', [
            'conversion_id' => $conversion->getKey(),
            'affiliate_id' => $conversion->affiliate_id,
            'currency' => $conversion->commission_currency,
            'operation' => $operation,
            'requested_minor' => $requestedMinor,
            'applied_minor' => $appliedMinor,
        ]);

        HoldingShortfallDetected::dispatch((string) $conversion->getKey(), (string) $conversion->affiliate_id, $conversion->commission_currency, $operation, $requestedMinor, $appliedMinor);
    }

    private function resolveStatus(AffiliateConversion $conversion): ConversionStatus
    {
        return ConversionStatus::fromString($conversion->status, $conversion);
    }

    private static function createBalance(Affiliate $affiliate, string $currency): AffiliateBalance
    {
        $connection = (new AffiliateBalance)->getConnection();

        try {
            return $connection->transaction(static fn (): AffiliateBalance => AffiliateBalance::create([
                'affiliate_id' => $affiliate->id,
                'available_minor' => 0,
                'holding_minor' => 0,
                'lifetime_earnings_minor' => 0,
                'minimum_payout_minor' => PayoutMinimums::forCurrency($currency),
                'currency' => $currency,
            ]), 1);
        } catch (QueryException $exception) {
            // Concurrent first conversion in this currency: the unique
            // (affiliate_id, currency) row already exists, so reuse it.
            // The nested savepoint keeps the caller's outer transaction
            // usable on PostgreSQL after the unique violation.
            if (! in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true)) {
                throw $exception;
            }

            return AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->where('currency', $currency)->firstOrFail();
        }
    }
}
