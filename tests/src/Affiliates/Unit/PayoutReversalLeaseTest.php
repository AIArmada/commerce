<?php

declare(strict_types=1);

use AIArmada\Affiliates\Contracts\PayoutProcessorInterface;
use AIArmada\Affiliates\Contracts\PayoutProcessorRegistry;
use AIArmada\Affiliates\Data\PayoutRequest;
use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Data\PayoutStatusResult;
use AIArmada\Affiliates\Drivers\Payouts\PayoutProcessorFactory;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReversal;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Features\Payouts\States\FailedPayout;
use AIArmada\Affiliates\Features\Payouts\States\ProcessingPayout;
use AIArmada\Affiliates\States\Active;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ReversalLeaseProbeProcessor implements PayoutProcessorInterface
{
    public int $cancellations = 0;

    public int $cancelTransactionLevel = -1;

    public function __construct(private readonly bool $result = true) {}

    public function process(PayoutRequest $payout): PayoutResult
    {
        return PayoutResult::failure('not used');
    }

    public function getStatus(PayoutRequest $payout): PayoutStatusResult
    {
        return new PayoutStatusResult('unknown');
    }

    public function cancel(PayoutRequest $payout): bool
    {
        $this->cancellations++;
        $this->cancelTransactionLevel = DB::transactionLevel();

        return $this->result;
    }

    public function getEstimatedArrival(PayoutRequest $payout): ?DateTimeInterface
    {
        return null;
    }

    public function getFees(int $amountMinor, string $currency): int
    {
        return 0;
    }

    public function validateDetails(array $details): array
    {
        return [];
    }

    public function getIdentifier(): string
    {
        return 'reversal_probe';
    }
}

function createReversalLeasePayout(): AffiliatePayout
{
    $affiliate = Affiliate::create([
        'code' => 'REVLEASE-' . uniqid(),
        'name' => 'Reversal Lease Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->getKey(),
        'type' => PayoutMethodType::StripeConnect,
        'details' => ['stripe_account_id' => 'acct_123456'],
        'is_default' => true,
    ]);

    $operation = AffiliatePayoutOperation::create([
        'affiliate_id' => $affiliate->getKey(),
        'operation_key' => 'reversal:' . uniqid(),
        'status' => 'submitted',
        'amount_minor' => 10000,
        'currency' => 'USD',
        'claimed_at' => now(),
    ]);

    $payout = AffiliatePayout::create([
        'affiliate_payout_operation_id' => $operation->getKey(),
        'reference' => 'REVLEASE-' . uniqid(),
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
        'total_minor' => 10000,
        'currency' => 'USD',
        'status' => ProcessingPayout::class,
        'external_reference' => 'tr_reversal',
    ]);

    $operation->forceFill(['affiliate_payout_id' => $payout->getKey()])->save();

    return $payout;
}

function useReversalProbe(ReversalLeaseProbeProcessor $processor): void
{
    $factory = new PayoutProcessorFactory;
    $factory->register(PayoutMethodType::StripeConnect->value, ReversalLeaseProbeProcessor::class);
    app()->instance(PayoutProcessorRegistry::class, $factory);
    app()->instance(ReversalLeaseProbeProcessor::class, $processor);
}

test('reversal keeps provider transport outside the database transaction and releases the payout', function (): void {
    $payout = createReversalLeasePayout();
    $probe = new ReversalLeaseProbeProcessor;
    useReversalProbe($probe);
    $baselineTransactionLevel = DB::transactionLevel();

    expect(app(PayoutReversal::class)->reverse((string) $payout->getKey()))->toBeTrue();

    $operation = AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id)->fresh();

    expect($probe->cancellations)->toBe(1)
        ->and($probe->cancelTransactionLevel)->toBe($baselineTransactionLevel)
        ->and($payout->fresh()->status->equals(FailedPayout::class))->toBeTrue()
        ->and($operation->status)->toBe('reversed');
});

test('an active reversal lease blocks overlapping provider calls', function (): void {
    $payout = createReversalLeasePayout();
    $operation = AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id);
    $operation->forceFill([
        'status' => 'reversing',
        'lease_expires_at' => CarbonImmutable::now()->addMinutes(5),
    ])->save();

    $probe = new ReversalLeaseProbeProcessor;
    useReversalProbe($probe);

    expect(app(PayoutReversal::class)->reverse((string) $payout->getKey()))->toBeFalse()
        ->and($probe->cancellations)->toBe(0)
        ->and($payout->fresh()->status->equals(ProcessingPayout::class))->toBeTrue();
});

test('an ambiguous reversal keeps the payout reserved for an idempotent reversal retry', function (): void {
    $payout = createReversalLeasePayout();
    $probe = new ReversalLeaseProbeProcessor(false);
    useReversalProbe($probe);

    expect(app(PayoutReversal::class)->reverse((string) $payout->getKey()))->toBeFalse();

    $operation = AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id)->fresh();

    expect($payout->fresh()->status->equals(ProcessingPayout::class))->toBeTrue()
        ->and($operation->status)->toBe('reversing')
        ->and($operation->last_error_code)->toBe('REVERSAL_OUTCOME_UNKNOWN')
        ->and($operation->lease_expires_at)->toBeNull();
});
