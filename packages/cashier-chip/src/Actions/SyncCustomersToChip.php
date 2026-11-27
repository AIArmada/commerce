<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Actions;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Contracts\CustomerOwnerResolverInterface;
use AIArmada\CashierChip\Exceptions\CustomerAlreadyCreated;
use AIArmada\CashierChip\Support\DefaultCustomerOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class SyncCustomersToChip
{
    public function __construct(private CustomerOwnerResolverInterface $ownerResolver, private DefaultCustomerOwnerResolver $defaultOwnerResolver) {}

    /**
     * @return array{synced: int, skipped: int, failed: int}
     */
    public function handle(int $chunkSize): array
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

        $query = $model::query();

        if ((bool) config('cashier-chip.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
            $resolver = method_exists($query->getModel(), 'ownerScopeConfig') ? $this->defaultOwnerResolver : $this->ownerResolver;
            $query = $resolver->scopeQuery($query, $owner);
        }

        $query->whereDoesntHave('chipCustomerLink')
            ->chunkById(max(1, $chunkSize), function (EloquentCollection $customers) use (&$synced, &$skipped, &$failed, &$failures): void {
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
                                'exception' => $exception::class,
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
