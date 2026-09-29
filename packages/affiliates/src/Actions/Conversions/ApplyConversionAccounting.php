<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Events\HoldingShortfallDetected;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\QualifiedConversion;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\Affiliates\States\ReversedConversion;
use AIArmada\Affiliates\Support\PayoutMinimums;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsAction;

final class ApplyConversionAccounting
{
    use AsAction;

    /** @var array<string, bool> */
    private static array $balancesTableMemo = [];

    public static function balancesSyncEnabled(): bool
    {
        $table = (new AffiliateBalance)->getTable();

        return self::$balancesTableMemo[$table] ??= Schema::hasTable($table);
    }

    public function handle(AffiliateConversion $conversion, ?ConversionStatus $previousStatus = null): void
    {
        if (! self::balancesSyncEnabled()) {
            return;
        }

        $affiliate = $conversion->affiliate()->first();

        if (! $affiliate) {
            return;
        }

        DB::transaction(function () use ($affiliate, $conversion, $previousStatus): void {
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

            $balance = $lockedAffiliate->balances()->where('currency', $currency)->lockForUpdate()->first()
                ?? self::createBalance($lockedAffiliate, $currency);

            if ($previousStatus === null) {
                $this->applyCreationAccounting($lockedConversion, $balance);
            } else {
                $this->applyTransitionAccounting($lockedConversion, $balance, $previousStatus);
            }
        });
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
            $balance->addToHolding($conversion->commission_minor);
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
                $balance->releaseFromHolding($releaseMinor);
                $releasedActual = $holdingBeforeRelease - (int) $balance->holding_minor;

                if ($releasedActual < $releaseMinor) {
                    $this->reportHoldingShortfall($conversion, 'release', $releaseMinor, $releasedActual);
                }

                $excessMinor = $heldMinor - $releaseMinor;

                if ($excessMinor > 0) {
                    $holdingBeforeVoid = (int) $balance->holding_minor;
                    $balance->voidFromHolding($excessMinor);
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
            $balance->voidFromAvailable($conversion->commission_minor);
        }

        if ($previousStatus->equals(ApprovedConversion::class) && $newStatus->equals(ReversedConversion::class)) {
            $balance->voidFromAvailable($conversion->commission_minor);
        }

        if ($previousStatus->equals(PaidConversion::class) && $newStatus->equals(ReversedConversion::class)) {
            $balance->clawbackFromAvailable($conversion->commission_minor);
        }

        if ($newStatus->equals(PaidConversion::class) && $conversion->affiliate_payout_id === null) {
            $balance->deductFromAvailable($conversion->commission_minor);
        }
    }

    private function voidRecordedHold(AffiliateConversion $conversion, AffiliateBalance $balance): void
    {
        // Lifetime un-counting rides inside voidFromHolding and does not
        // depend on the pool state; only the holding move can clamp.
        $heldMinor = (int) $conversion->held_minor;
        $holdingBefore = (int) $balance->holding_minor;
        $balance->voidFromHolding($heldMinor);
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

        HoldingShortfallDetected::dispatch($conversion, $operation, $requestedMinor, $appliedMinor);
    }

    private function resolveStatus(AffiliateConversion $conversion): ConversionStatus
    {
        return ConversionStatus::fromString($conversion->status, $conversion);
    }

    private static function createBalance(Affiliate $affiliate, string $currency): AffiliateBalance
    {
        try {
            return AffiliateBalance::create([
                'affiliate_id' => $affiliate->id,
                'available_minor' => 0,
                'holding_minor' => 0,
                'lifetime_earnings_minor' => 0,
                'minimum_payout_minor' => PayoutMinimums::forCurrency($currency),
                'currency' => $currency,
            ]);
        } catch (QueryException $exception) {
            // Concurrent first conversion in this currency: the unique
            // (affiliate_id, currency) row already exists, so reuse it.
            if (! in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true)) {
                throw $exception;
            }

            return $affiliate->balances()->where('currency', $currency)->firstOrFail();
        }
    }
}
