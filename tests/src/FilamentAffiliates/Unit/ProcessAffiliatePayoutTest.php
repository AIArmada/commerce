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
use AIArmada\Affiliates\Features\Conversions\Actions\ApplyConversionAccounting;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycle;
use AIArmada\Affiliates\Features\Payouts\Actions\CreatePayout;
use AIArmada\Affiliates\Features\Payouts\Actions\ProcessAffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\ProcessingPayout;
use AIArmada\Affiliates\States\ReversedConversion;
use Illuminate\Support\Facades\DB;

class ProcessAffiliatePayoutFakeSuccessProcessor implements PayoutProcessorInterface
{
    public function process(PayoutRequest $payout): PayoutResult
    {
        return PayoutResult::success('PROV-REF-' . $payout->id);
    }

    public function getStatus(PayoutRequest $payout): PayoutStatusResult
    {
        return new PayoutStatusResult('completed');
    }

    public function cancel(PayoutRequest $payout): bool
    {
        return true;
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

function createProcessPayout(Affiliate $affiliate, array $attributes = []): AffiliatePayout
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

    app(ApplyConversionAccounting::class)->handle($conversion);

    return app(CreatePayout::class)->handle([$conversion->id], $attributes);
}

function useFakeSuccessProcessor(): void
{
    $factory = new PayoutProcessorFactory;
    $factory->register(PayoutMethodType::BankTransfer->value, ProcessAffiliatePayoutFakeSuccessProcessor::class);
    app()->instance(PayoutProcessorRegistry::class, $factory);
}

final class ProcessAffiliatePayoutFakeThrowingProcessor implements PayoutProcessorInterface
{
    public function process(PayoutRequest $payout): PayoutResult
    {
        throw new InvalidArgumentException('Processor exploded.');
    }

    public function getStatus(PayoutRequest $payout): PayoutStatusResult
    {
        return new PayoutStatusResult('failed');
    }

    public function cancel(PayoutRequest $payout): bool
    {
        return true;
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
        return 'fake_throwing';
    }
}

test('a gate-blocked completion parks as unknown with the gate reason', function (): void {
    $affiliate = createProcessPayoutAffiliate();
    $payout = createProcessPayout($affiliate);

    app(AffiliateLifecycle::class)->disable($affiliate->id);
    useFakeSuccessProcessor();
    $processor = Mockery::mock(ProcessAffiliatePayoutFakeSuccessProcessor::class)->makePartial();
    $processor->shouldNotReceive('process');
    app()->instance(ProcessAffiliatePayoutFakeSuccessProcessor::class, $processor);

    $result = app(ProcessAffiliatePayout::class)->handle($payout);

    expect($result->isUnknown())->toBeTrue();

    $operation = AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id)->fresh();
    // Same-second events tie on created_at: select the blocked outcome
    // by its notes prefix instead of relying on latest() order.
    $blockedEvent = AffiliatePayoutEvent::query()->where('affiliate_payout_id', $payout->getKey())->where('notes', 'like', 'Completion blocked:%')->first();

    expect($payout->fresh()->status->equals(ProcessingPayout::class))->toBeTrue()
        ->and($operation->status)->toBe('unknown')
        ->and($operation->last_error_code)->toBe('PAYOUT_COMPLETION_BLOCKED')
        ->and($blockedEvent)->not->toBeNull()
        ->and($blockedEvent->notes)->toContain('can no longer receive payouts');
});

test('valid provider processing settles only the reserved conversions', function (): void {
    $affiliate = createProcessPayoutAffiliate();
    $payout = createProcessPayout($affiliate);
    useFakeSuccessProcessor();

    $result = app(ProcessAffiliatePayout::class)->handle($payout);

    expect($result->isSuccess())->toBeTrue()
        ->and($payout->fresh()->status->equals(CompletedPayout::class))->toBeTrue()
        ->and($payout->conversions()->firstOrFail()->status->equals(PaidConversion::class))->toBeTrue()
        ->and($affiliate->balanceFor('USD')->available_minor)->toBe(0);
});

test('corrupt conversion links never reach a provider submission', function (bool $foreignAffiliate, bool $retry): void {
    $affiliate = createProcessPayoutAffiliate();
    $payout = createProcessPayout($affiliate);
    $foreign = $foreignAffiliate ? createProcessPayoutAffiliate() : $affiliate;
    $conversion = AffiliateConversion::create([
        'affiliate_id' => $foreign->id,
        'affiliate_code' => $foreign->code,
        'external_reference' => 'CORRUPT-' . uniqid(),
        'value_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => $foreignAffiliate ? 'USD' : 'EUR',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);
    app(ApplyConversionAccounting::class)->handle($conversion);
    DB::table($conversion->getTable())->where('id', $conversion->id)
        ->update(['affiliate_payout_id' => $payout->id]);

    if ($retry) {
        $payout->forceFill(['status' => ProcessingPayout::class])->save();
        AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id)->update(['status' => 'unknown', 'lease_expires_at' => null]);
    }

    useFakeSuccessProcessor();
    $processor = Mockery::mock(ProcessAffiliatePayoutFakeSuccessProcessor::class)->makePartial();
    $processor->shouldNotReceive('process');
    if ($retry) {
        $processor->shouldReceive('getStatus')->once()->andReturn(new PayoutStatusResult('not_found'));
    }
    app()->instance(ProcessAffiliatePayoutFakeSuccessProcessor::class, $processor);

    $result = app(ProcessAffiliatePayout::class)->handle($payout);

    expect($result->isUnknown())->toBeTrue()
        ->and($result->failureCode)->toBe('PAYOUT_COMPLETION_BLOCKED')
        ->and(AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id)->funds_released_at)->toBeNull()
        ->and($conversion->fresh()->status->equals(ApprovedConversion::class))->toBeTrue();
})->with([true, false])->with([false, true]);

test('provider processing cannot complete a payout after its affiliate becomes Dawah-only', function (): void {
    $affiliate = createProcessPayoutAffiliate();
    $payout = createProcessPayout($affiliate);
    $affiliate->forceFill(['purpose' => 'share_member'])->save();
    useFakeSuccessProcessor();
    $processor = Mockery::mock(ProcessAffiliatePayoutFakeSuccessProcessor::class)->makePartial();
    $processor->shouldNotReceive('process');
    app()->instance(ProcessAffiliatePayoutFakeSuccessProcessor::class, $processor);

    $result = app(ProcessAffiliatePayout::class)->handle($payout);
    $blockedEvent = AffiliatePayoutEvent::query()->where('affiliate_payout_id', $payout->getKey())->where('notes', 'like', 'Completion blocked:%')->first();

    expect($result->isUnknown())->toBeTrue()
        ->and($payout->fresh()->status->equals(ProcessingPayout::class))->toBeTrue()
        ->and(AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id)->last_error_code)->toBe('PAYOUT_COMPLETION_BLOCKED')
        ->and($blockedEvent?->notes)->toContain('non-commercial affiliate payout cannot be completed');
});

test('provider submission refuses drift from the reserved conversions', function (string $drift): void {
    $affiliate = createProcessPayoutAffiliate();
    $payout = createProcessPayout($affiliate);
    $conversion = $payout->conversions()->firstOrFail();

    match ($drift) {
        'payout_amount' => $payout->update(['total_minor' => 10000]),
        'conversion_amount' => DB::table($conversion->getTable())->where('id', $conversion->id)->update(['commission_minor' => 10000]),
        'detached' => DB::table($conversion->getTable())->where('id', $conversion->id)->update(['affiliate_payout_id' => null]),
        'status' => DB::table($conversion->getTable())->where('id', $conversion->id)->update(['status' => ReversedConversion::value()]),
    };

    useFakeSuccessProcessor();
    $processor = Mockery::mock(ProcessAffiliatePayoutFakeSuccessProcessor::class)->makePartial();
    $processor->shouldNotReceive('process');
    app()->instance(ProcessAffiliatePayoutFakeSuccessProcessor::class, $processor);

    $result = app(ProcessAffiliatePayout::class)->handle($payout);

    expect($result->isUnknown())->toBeTrue()
        ->and($result->failureCode)->toBe('PAYOUT_COMPLETION_BLOCKED')
        ->and($payout->fresh()->status->equals(CompletedPayout::class))->toBeFalse();
})->with(['payout_amount', 'conversion_amount', 'detached', 'status']);

test('a valid disabled-affiliate override is stamped only after provider completion', function (): void {
    $affiliate = createProcessPayoutAffiliate();
    app(AffiliateLifecycle::class)->disable($affiliate->id);
    $payout = createProcessPayout($affiliate->fresh(), ['payout_override_reason' => 'Earned before closure']);
    useFakeSuccessProcessor();
    $processor = Mockery::mock(ProcessAffiliatePayoutFakeSuccessProcessor::class)->makePartial();
    $processor->shouldReceive('process')->once()->andReturnUsing(function (PayoutRequest $claimed): PayoutResult {
        expect(AffiliatePayout::query()->findOrFail($claimed->id)->metadata['payout_override']['override_completed_at'] ?? null)->toBeNull();

        return PayoutResult::success('OVERRIDE-REF');
    });
    app()->instance(ProcessAffiliatePayoutFakeSuccessProcessor::class, $processor);

    $result = app(ProcessAffiliatePayout::class)->handle($payout);

    expect($result->isSuccess())->toBeTrue()
        ->and($payout->fresh()->metadata['payout_override']['override_completed_at'])->not->toBeNull();
});

test('a non-gate processor exception keeps the generic processing code', function (): void {
    $affiliate = createProcessPayoutAffiliate();
    $payout = createProcessPayout($affiliate);

    $factory = new PayoutProcessorFactory;
    $factory->register(PayoutMethodType::BankTransfer->value, ProcessAffiliatePayoutFakeThrowingProcessor::class);
    app()->instance(PayoutProcessorRegistry::class, $factory);

    $result = app(ProcessAffiliatePayout::class)->handle($payout);

    expect($result->isUnknown())->toBeTrue()
        ->and(AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id)->fresh()->last_error_code)->toBe('PAYOUT_PROCESSING_EXCEPTION')
        ->and(AffiliatePayoutEvent::query()->where('affiliate_payout_id', $payout->getKey())->where('notes', 'like', 'Completion blocked:%')->exists())->toBeFalse();
});

test('a late provider response cannot overwrite a newer payout lease', function (): void {
    $affiliate = createProcessPayoutAffiliate();
    $payout = createProcessPayout($affiliate);

    $factory = new PayoutProcessorFactory;
    $factory->register(PayoutMethodType::BankTransfer->value, ProcessAffiliatePayoutFakeSuccessProcessor::class);
    app()->instance(PayoutProcessorRegistry::class, $factory);

    $processor = Mockery::mock(ProcessAffiliatePayoutFakeSuccessProcessor::class)->makePartial();
    $processor->shouldReceive('process')->once()->andReturnUsing(function (PayoutRequest $claimedPayout): PayoutResult {
        AffiliatePayoutOperation::query()
            ->where('affiliate_payout_id', $claimedPayout->id)
            ->update(['lease_expires_at' => now()->addHour()]);

        return PayoutResult::unknown('LATE_PROVIDER_RESPONSE');
    });
    app()->instance(ProcessAffiliatePayoutFakeSuccessProcessor::class, $processor);

    $result = app(ProcessAffiliatePayout::class)->handle($payout);
    $operation = AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id)->fresh();

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
    app()->instance(PayoutProcessorRegistry::class, $factory);

    $processor = Mockery::mock(ProcessAffiliatePayoutFakeSuccessProcessor::class)->makePartial();
    $processor->shouldReceive('process')->once()->andReturnUsing(function (PayoutRequest $claimedPayout): PayoutResult {
        $operation = AffiliatePayoutOperation::query()
            ->where('affiliate_payout_id', $claimedPayout->id)
            ->firstOrFail();
        $operation->forceFill(['status' => 'completed', 'lease_expires_at' => null])->save();
        AffiliatePayout::query()->findOrFail($claimedPayout->id)->forceFill(['status' => CompletedPayout::class])->save();

        return PayoutResult::unknown('LATE_PROVIDER_RESPONSE');
    });
    app()->instance(ProcessAffiliatePayoutFakeSuccessProcessor::class, $processor);

    app(ProcessAffiliatePayout::class)->handle($payout);

    expect($payout->fresh()->status->equals(CompletedPayout::class))->toBeTrue()
        ->and(AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id)->fresh()->status)->toBe('completed');
});

test('missing payout method does not release funds after provider submission', function (): void {
    $affiliate = createProcessPayoutAffiliate();
    $payout = createProcessPayout($affiliate);
    $operation = AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id);

    AffiliatePayoutMethod::query()->where('affiliate_id', $affiliate->getKey())->delete();
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
