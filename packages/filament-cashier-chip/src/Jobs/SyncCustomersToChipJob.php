<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Jobs;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Exceptions\CustomerAlreadyCreated;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\FilamentCashierChip\Resources\CustomerResource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SyncCustomersToChipJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public readonly ?OwnerJobContext $ownerContext = null,
        public readonly int $chunkSize = 100,
    ) {}

    public function handle(): void
    {
        OwnerContext::withOwner(
            $this->ownerContext?->toOwnerModel(),
            fn (): array => $this->syncChunked(),
        );
    }

    /**
     * @return array{synced: int, skipped: int, failed: int}
     */
    private function syncChunked(): array
    {
        $model = Cashier::$customerModel;

        if (! is_subclass_of($model, Model::class)
            || ! method_exists($model, 'chipCustomerLink')
            || ! method_exists($model, 'createAsChipCustomer')) {
            Log::warning('SyncCustomersToChipJob skipped: customer model has no CHIP support.', [
                'model' => $model,
            ]);

            return ['synced' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $synced = 0;
        $skipped = 0;
        $failed = 0;
        $failures = [];

        CustomerResource::getEloquentQuery()
            ->whereDoesntHave('chipCustomerLink')
            ->chunkById(max(1, $this->chunkSize), function (EloquentCollection $customers) use (&$synced, &$skipped, &$failed, &$failures): void {
                foreach ($customers as $customer) {
                    if (! method_exists($customer, 'createAsChipCustomer')) {
                        $skipped++;

                        continue;
                    }

                    try {
                        $customer->createAsChipCustomer();
                        $synced++;
                    } catch (CustomerAlreadyCreated) {
                        $skipped++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $failed++;

                        if (count($failures) < 10) {
                            $failures[] = [
                                'customer' => (string) $customer->getKey(),
                                'error' => $exception->getMessage(),
                            ];
                        }
                    }
                }
            });

        Log::info('SyncCustomersToChipJob completed.', [
            'synced' => $synced,
            'skipped' => $skipped,
            'failed' => $failed,
            'failures' => $failures,
        ]);

        return ['synced' => $synced, 'skipped' => $skipped, 'failed' => $failed];
    }
}
