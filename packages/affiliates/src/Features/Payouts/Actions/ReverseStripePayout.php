<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Contracts\PayoutProcessorRegistry;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReversal;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Features\Payouts\Queries\PayoutOperationQuery;
use AIArmada\Affiliates\Features\Payouts\Queries\PayoutRequestQuery;
use AIArmada\Affiliates\Features\Payouts\States\FailedPayout;
use AIArmada\Affiliates\Features\Payouts\States\ProcessingPayout;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final readonly class ReverseStripePayout implements PayoutReversal
{
    public function __construct(
        private PayoutProcessorRegistry $processors,
        private UpdatePayoutStatus $updatePayoutStatus,
    ) {}

    public function reverse(string $payoutId): bool
    {
        $connection = (new AffiliatePayout)->getConnection();

        if ($connection !== (new AffiliatePayoutOperation)->getConnection()
            || $connection !== (new AffiliatePayoutEvent)->getConnection()) {
            throw new LogicException('Payout reversal requires a shared database connection.');
        }

        $claim = $connection->transaction(function () use ($payoutId): array {
            $payout = AffiliatePayout::query()
                ->forOwner(includeGlobal: (bool) config('affiliates.owner.include_global', false) && ! OwnerScopeOverride::suppressIncludeGlobal())
                ->lockForUpdate()
                ->findOrFail($payoutId);

            return OwnerContext::withOwner($this->ownerContext($payout), function () use ($payout): array {
                $operation = PayoutOperationQuery::forPayout($payout, $payout->owner)->lockForUpdate()->first();

                if (! $operation instanceof AffiliatePayoutOperation) {
                    return ['result' => false];
                }

                if ($operation->status === 'reversed') {
                    return ['result' => true];
                }

                if (! $payout->status->equals(ProcessingPayout::class)) {
                    return ['result' => false];
                }

                if (! in_array($operation->status, ['submitting', 'submitted', 'unknown', 'reversing'], true)) {
                    return ['result' => false];
                }

                if ($operation->lease_expires_at?->isFuture()) {
                    return ['result' => false];
                }

                if ($payout->external_reference === null && $operation->provider_reference === null) {
                    return ['result' => false];
                }

                $operation->forceFill([
                    'status' => 'reversing',
                    'last_error_code' => null,
                    'lease_expires_at' => CarbonImmutable::now()->addMinutes(5),
                ])->save();

                return [
                    'request' => PayoutRequestQuery::fromPayout($payout, []),
                    'lease' => $operation->fresh()?->lease_expires_at?->toISOString(),
                ];
            });
        }, attempts: 3);

        if (array_key_exists('result', $claim)) {
            return $claim['result'];
        }

        $cancelled = $this->processors->make('stripe_connect')->cancel($claim['request']);

        return $connection->transaction(function () use ($payoutId, $claim, $cancelled): bool {
            $payout = AffiliatePayout::query()->withoutOwnerScope()->lockForUpdate()->findOrFail($payoutId);

            return OwnerContext::withOwner($this->ownerContext($payout), function () use ($payout, $claim, $cancelled): bool {
                $operation = PayoutOperationQuery::forPayout($payout, $payout->owner)->lockForUpdate()->first();

                if (! $operation instanceof AffiliatePayoutOperation) {
                    return false;
                }

                if ($operation->status === 'reversed') {
                    return true;
                }

                if ($operation->status !== 'reversing' || $operation->lease_expires_at?->toISOString() !== $claim['lease']) {
                    return false;
                }

                if (! $cancelled) {
                    $operation->forceFill([
                        'last_error_code' => 'REVERSAL_OUTCOME_UNKNOWN',
                        'lease_expires_at' => null,
                    ])->save();

                    return false;
                }

                $this->updatePayoutStatus->handle(
                    $payout,
                    FailedPayout::value(),
                    'Provider transfer reversed.',
                    ['provider' => 'stripe', 'provider_status' => 'reversed'],
                );

                $operation->refresh()->forceFill([
                    'status' => 'reversed',
                    'last_error_code' => null,
                    'lease_expires_at' => null,
                ])->save();

                return true;
            });
        }, attempts: 3);
    }

    private function ownerContext(AffiliatePayout $payout): ?Model
    {
        return OwnerContext::fromTypeAndId($payout->owner_type, $payout->owner_id);
    }
}
