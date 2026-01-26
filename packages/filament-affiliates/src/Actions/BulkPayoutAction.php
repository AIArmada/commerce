<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Services\Payouts\PayoutProcessorFactory;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use Exception;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class BulkPayoutAction extends BulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Process Payouts');
        $this->icon('heroicon-o-banknotes');
        $this->color('success');
        $this->requiresConfirmation();
        $this->modalHeading('Process Selected Payouts');
        $this->modalDescription('Are you sure you want to process these payouts? This will initiate payment transfers.');

        $this->action(function (Collection $records): void {
            $factory = app(PayoutProcessorFactory::class);
            $processed = 0;
            $failed = 0;

            foreach ($records as $payout) {
                if (! $payout instanceof AffiliatePayout) {
                    continue;
                }

                if (! $payout->status->equals(PendingPayout::class)) {
                    continue;
                }

                try {
                    DB::transaction(function () use ($payout, $factory, &$processed, &$failed): void {
                        $payout->update(['status' => ProcessingPayout::class]);

                        $payoutMethod = $payout->affiliate->payoutMethods()
                            ->where('is_default', true)
                            ->first();

                        if (! $payoutMethod) {
                            $payout->update(['status' => FailedPayout::class]);
                            $payout->events()->create([
                                'from_status' => ProcessingPayout::value(),
                                'to_status' => FailedPayout::value(),
                                'notes' => 'No default payout method configured',
                            ]);
                            $failed++;

                            return;
                        }

                        $processor = $factory->make($payoutMethod->type->value);
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

                            $processed++;
                        } else {
                            $payout->update(['status' => FailedPayout::class]);
                            $payout->events()->create([
                                'from_status' => ProcessingPayout::value(),
                                'to_status' => FailedPayout::value(),
                                'notes' => $result->failureReason,
                            ]);
                            $failed++;
                        }
                    });
                } catch (Exception $e) {
                    $fromStatus = $payout->status?->getValue();

                    $payout->update(['status' => FailedPayout::class]);
                    $payout->events()->create([
                        'from_status' => $fromStatus,
                        'to_status' => FailedPayout::value(),
                        'notes' => $e->getMessage(),
                    ]);
                    $failed++;
                }
            }

            if ($processed > 0) {
                $this->success();
            }

            $this->sendSuccessNotification();
        });

        $this->deselectRecordsAfterCompletion();
    }

    public static function getDefaultName(): ?string
    {
        return 'bulk_process_payouts';
    }
}
