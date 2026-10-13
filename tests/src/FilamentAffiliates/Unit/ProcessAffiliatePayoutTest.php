<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\DisableAffiliate;
use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Actions\Payouts\CreatePayout;
use AIArmada\Affiliates\Actions\Payouts\ProcessAffiliatePayout;
use AIArmada\Affiliates\Contracts\PayoutProcessorInterface;
use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Services\Payouts\PayoutProcessorFactory;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\ProcessingPayout;

class ProcessAffiliatePayoutFakeSuccessProcessor implements PayoutProcessorInterface
{
    public function process(AffiliatePayout $payout): PayoutResult
    {
        return PayoutResult::success('PROV-REF-' . $payout->getKey());
    }

    public function getStatus(AffiliatePayout $payout): string
    {
        return 'completed';
    }

    public function cancel(AffiliatePayout $payout): bool
    {
        return true;
    }

    public function getEstimatedArrival(AffiliatePayout $payout): ?DateTimeInterface
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
        return 'fake_success';
    }
}

function createProcessPayoutAffiliate(): Affiliate
{
    $affiliate = Affiliate::create([
        'code' => 'PROCPAY-' . uniqid(),
        'name' => 'Process Payout Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->getKey(),
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Test Bank', 'account_number' => '12345678'],
        'verified_at' => now(),
        'is_default' => true,
    ]);

    return $affiliate;
}

function createProcessPayout(Affiliate $affiliate): AffiliatePayout
{
    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'PROCPAY-' . uniqid(),
        'value_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    ApplyConversionAccounting::run($conversion);

    return CreatePayout::run([$conversion->id]);
}

function useFakeSuccessProcessor(): void
{
    $factory = new PayoutProcessorFactory;
    $factory->register(PayoutMethodType::BankTransfer->value, ProcessAffiliatePayoutFakeSuccessProcessor::class);
    app()->instance(PayoutProcessorFactory::class, $factory);
}

final class ProcessAffiliatePayoutFakeThrowingProcessor implements PayoutProcessorInterface
{
    public function process(AffiliatePayout $payout): PayoutResult
    {
        throw new InvalidArgumentException('Processor exploded.');
    }

    public function getStatus(AffiliatePayout $payout): string
    {
        return 'failed';
    }

    public function cancel(AffiliatePayout $payout): bool
    {
        return true;
    }

    public function getEstimatedArrival(AffiliatePayout $payout): ?DateTimeInterface
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
        return 'fake_throwing';
    }
}

test('a gate-blocked completion parks as unknown with the gate reason', function (): void {
    $affiliate = createProcessPayoutAffiliate();
    $payout = createProcessPayout($affiliate);

    DisableAffiliate::run($affiliate->fresh());
    useFakeSuccessProcessor();

    $result = app(ProcessAffiliatePayout::class)->handle($payout);

    expect($result->isUnknown())->toBeTrue();

    $operation = $payout->operation()->first()->fresh();
    // Same-second events tie on created_at: select the blocked outcome
    // by its notes prefix instead of relying on latest() order.
    $blockedEvent = $payout->events()->where('notes', 'like', 'Completion blocked:%')->first();

    expect($payout->fresh()->status->equals(ProcessingPayout::class))->toBeTrue()
        ->and($operation->status)->toBe('unknown')
        ->and($operation->last_error_code)->toBe('PAYOUT_COMPLETION_BLOCKED')
        ->and($blockedEvent)->not->toBeNull()
        ->and($blockedEvent->notes)->toContain('can no longer receive payouts');
});

test('a non-gate processor exception keeps the generic processing code', function (): void {
    $affiliate = createProcessPayoutAffiliate();
    $payout = createProcessPayout($affiliate);

    $factory = new PayoutProcessorFactory;
    $factory->register(PayoutMethodType::BankTransfer->value, ProcessAffiliatePayoutFakeThrowingProcessor::class);
    app()->instance(PayoutProcessorFactory::class, $factory);

    $result = app(ProcessAffiliatePayout::class)->handle($payout);

    expect($result->isUnknown())->toBeTrue()
        ->and($payout->operation()->first()->fresh()->last_error_code)->toBe('PAYOUT_PROCESSING_EXCEPTION')
        ->and($payout->events()->where('notes', 'like', 'Completion blocked:%')->exists())->toBeFalse();
});

test('a late provider response cannot overwrite a newer payout lease', function (): void {
    $affiliate = createProcessPayoutAffiliate();
    $payout = createProcessPayout($affiliate);

    $factory = new PayoutProcessorFactory;
    $factory->register(PayoutMethodType::BankTransfer->value, ProcessAffiliatePayoutFakeSuccessProcessor::class);
    app()->instance(PayoutProcessorFactory::class, $factory);

    $processor = Mockery::mock(ProcessAffiliatePayoutFakeSuccessProcessor::class)->makePartial();
    $processor->shouldReceive('process')->once()->andReturnUsing(function (AffiliatePayout $claimedPayout): PayoutResult {
        AffiliatePayoutOperation::query()
            ->where('affiliate_payout_id', $claimedPayout->getKey())
            ->update(['lease_expires_at' => now()->addHour()]);

        return PayoutResult::unknown('LATE_PROVIDER_RESPONSE');
    });
    app()->instance(ProcessAffiliatePayoutFakeSuccessProcessor::class, $processor);

    $result = app(ProcessAffiliatePayout::class)->handle($payout);
    $operation = $payout->operation()->firstOrFail()->fresh();

    expect($result->isUnknown())->toBeTrue()
        ->and($payout->fresh()->status->equals(ProcessingPayout::class))->toBeTrue()
        ->and($operation->status)->toBe('submitting')
        ->and($operation->last_error_code)->toBeNull()
        ->and($operation->lease_expires_at->isAfter(now()->addMinutes(50)))->toBeTrue();
});

test('a late provider response cannot revert a completed payout', function (): void {
    $affiliate = createProcessPayoutAffiliate();
    $payout = createProcessPayout($affiliate);

    $factory = new PayoutProcessorFactory;
    $factory->register(PayoutMethodType::BankTransfer->value, ProcessAffiliatePayoutFakeSuccessProcessor::class);
    app()->instance(PayoutProcessorFactory::class, $factory);

    $processor = Mockery::mock(ProcessAffiliatePayoutFakeSuccessProcessor::class)->makePartial();
    $processor->shouldReceive('process')->once()->andReturnUsing(function (AffiliatePayout $claimedPayout): PayoutResult {
        $operation = AffiliatePayoutOperation::query()
            ->where('affiliate_payout_id', $claimedPayout->getKey())
            ->firstOrFail();
        $operation->forceFill(['status' => 'completed', 'lease_expires_at' => null])->save();
        $claimedPayout->forceFill(['status' => CompletedPayout::class])->save();

        return PayoutResult::unknown('LATE_PROVIDER_RESPONSE');
    });
    app()->instance(ProcessAffiliatePayoutFakeSuccessProcessor::class, $processor);

    app(ProcessAffiliatePayout::class)->handle($payout);

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeTrue()
        ->and($payout->operation()->firstOrFail()->fresh()->status)->toBe('completed');
});

test('missing payout method does not release funds after provider submission', function (): void {
    $affiliate = createProcessPayoutAffiliate();
    $payout = createProcessPayout($affiliate);
    $operation = $payout->operation()->firstOrFail();

    $affiliate->payoutMethods()->delete();
    $payout->forceFill(['status' => ProcessingPayout::class])->save();
    $operation->forceFill([
        'status' => 'submitted',
        'provider_reference' => 'provider-ref-submitted',
        'lease_expires_at' => null,
    ])->save();

    $result = app(ProcessAffiliatePayout::class)->handle($payout);
    $operation->refresh();

    expect($result->isUnknown())->toBeTrue()
        ->and($payout->fresh()->status->equals(ProcessingPayout::class))->toBeTrue()
        ->and($operation->status)->toBe('unknown')
        ->and($operation->last_error_code)->toBe('NO_DEFAULT_PAYOUT_METHOD')
        ->and($operation->funds_released_at)->toBeNull();
});
