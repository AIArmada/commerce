<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\DisableAffiliate;
use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Actions\Payouts\CreatePayout;
use AIArmada\Affiliates\Contracts\PayoutProcessorInterface;
use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Services\Payouts\PayoutProcessorFactory;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ProcessingPayout;
use AIArmada\FilamentAffiliates\Actions\ProcessAffiliatePayout;

final class ProcessAffiliatePayoutFakeSuccessProcessor implements PayoutProcessorInterface
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
