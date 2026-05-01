<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Services\Payouts\PayoutProcessorFactory;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProcessAffiliatePayout
{
    public function __construct(
        private readonly PayoutProcessorFactory $factory,
    ) {}

    public function handle(AffiliatePayout $payout): PayoutResult
    {
        if (! $payout->status->equals(PendingPayout::class)) {
            return PayoutResult::failure('Payout is not pending.');
        }

        try {
            return DB::transaction(function () use ($payout): PayoutResult {
                $payout->update(['status' => ProcessingPayout::class]);

                $payoutMethod = $payout->affiliate
                    ?->payoutMethods()
                    ->where('is_default', true)
                    ->first();

                if ($payoutMethod === null) {
                    $payout->update(['status' => FailedPayout::class]);
                    $payout->events()->create([
                        'from_status' => ProcessingPayout::value(),
                        'to_status' => FailedPayout::value(),
                        'notes' => 'No default payout method configured',
                    ]);

                    return PayoutResult::failure('No default payout method configured');
                }

                $processor = $this->factory->make($payoutMethod->type->value);
                $result = $processor->process($payout);

                if ($result->success) {
                    $payout->update([
                        'status' => CompletedPayout::class,
                        'paid_at' => now(),
                        'metadata' => array_merge(
                            $payout->metadata ?? [],
                            $result->metadata,
                            ['external_reference' => $result->externalReference],
                        ),
                    ]);

                    $payout->events()->create([
                        'from_status' => ProcessingPayout::value(),
                        'to_status' => CompletedPayout::value(),
                        'notes' => 'Payout processed successfully',
                    ]);

                    return $result;
                }

                $payout->update(['status' => FailedPayout::class]);
                $payout->events()->create([
                    'from_status' => ProcessingPayout::value(),
                    'to_status' => FailedPayout::value(),
                    'notes' => $result->failureReason ?? 'Payout processing failed',
                ]);

                return $result;
            });
        } catch (Throwable $throwable) {
            $fromStatus = $payout->status?->getValue();

            $payout->update(['status' => FailedPayout::class]);
            $payout->events()->create([
                'from_status' => $fromStatus,
                'to_status' => FailedPayout::value(),
                'notes' => $throwable->getMessage(),
            ]);

            return PayoutResult::failure($throwable->getMessage());
        }
    }
}