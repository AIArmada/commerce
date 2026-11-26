<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Features\Conversions\Actions\ApplyConversionAccounting;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Payouts\Actions\CreatePayout;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReconciliation;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReconciliationQueries;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Features\Payouts\States\CompletedPayout;
use AIArmada\Affiliates\Features\Payouts\States\ProcessingPayout;
use AIArmada\Affiliates\States\Active;
use Carbon\CarbonImmutable;

function createLeaseReconciliationPayout(): AffiliatePayout
{
    $affiliate = Affiliate::create([
        'code' => 'LEASE-' . uniqid(),
        'name' => 'Lease Reconciliation Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'LEASE-' . uniqid(),
        'value_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    app(ApplyConversionAccounting::class)->handle($conversion);

    return app(CreatePayout::class)->handle([$conversion->getKey()]);
}

function leaseReconciliationOperation(AffiliatePayout $payout): AffiliatePayoutOperation
{
    return AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id);
}

test('reconciliation ignores a payout with an active processing lease', function (): void {
    $payout = createLeaseReconciliationPayout();
    $operation = leaseReconciliationOperation($payout);

    $payout->forceFill(['status' => ProcessingPayout::class])->save();
    $operation->forceFill([
        'status' => 'submitted',
        'lease_expires_at' => CarbonImmutable::now()->addMinutes(5),
    ])->save();

    $changed = app(PayoutReconciliation::class)->reconcile((string) $payout->getKey(), 'completed');

    expect($changed)->toBeFalse()
        ->and($payout->fresh()->status->equals(ProcessingPayout::class))->toBeTrue()
        ->and($operation->fresh()->status)->toBe('submitted');
});

test('reconciliation proceeds once the processing lease has expired', function (): void {
    $payout = createLeaseReconciliationPayout();
    $operation = leaseReconciliationOperation($payout);

    $payout->forceFill(['status' => ProcessingPayout::class])->save();
    $operation->forceFill([
        'status' => 'submitted',
        'lease_expires_at' => CarbonImmutable::now()->subMinute(),
    ])->save();

    $changed = app(PayoutReconciliation::class)->reconcile((string) $payout->getKey(), 'completed');

    expect($changed)->toBeTrue()
        ->and($payout->fresh()->status->equals(CompletedPayout::class))->toBeTrue();
});

test('the reconciliation queue excludes active processing leases', function (): void {
    $payout = createLeaseReconciliationPayout();
    $operation = leaseReconciliationOperation($payout);

    $payout->forceFill(['status' => ProcessingPayout::class])->save();
    $operation->forceFill([
        'status' => 'submitted',
        'lease_expires_at' => CarbonImmutable::now()->addMinutes(5),
    ])->save();
    AffiliatePayout::query()->whereKey($payout->getKey())->update(['updated_at' => CarbonImmutable::now()->subHour()]);

    expect(app(PayoutReconciliationQueries::class)->payoutIdsNeedingReconciliation())->toBeEmpty();

    $operation->forceFill(['lease_expires_at' => CarbonImmutable::now()->subMinute()])->save();

    expect(app(PayoutReconciliationQueries::class)->payoutIdsNeedingReconciliation())->toContain($payout->getKey());
});
