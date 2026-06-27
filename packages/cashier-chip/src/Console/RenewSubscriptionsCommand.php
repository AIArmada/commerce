<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Console;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Events\SubscriptionRenewed;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RenewSubscriptionsCommand extends Command
{
    protected $signature = 'cashier-chip:renew-subscriptions 
                            {--dry-run : Show what would be renewed without actually charging}
                            {--grace-hours=0 : Hours of grace period before considering subscription due}';

    protected $description = 'Process CHIP subscription renewals by charging recurring tokens';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $graceHours = (int) $this->option('grace-hours');

        $this->info('Processing CHIP subscription renewals...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No charges will be made');
        }

        /** @var array{renewed: int, failed: int}|null $result */
        $result = $this->batchRunner()->run(
            fn (): array => $this->processRenewals((bool) $dryRun, $graceHours)
        );

        $result = $result ?? ['renewed' => 0, 'failed' => 0];

        $this->newLine();
        $this->info("Renewal complete: {$result['renewed']} renewed, {$result['failed']} failed.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function batchRunner(): OwnerBatchRunner
    {
        return new OwnerBatchRunner(Subscription::class, [
            'enabled' => 'cashier-chip.features.owner.enabled',
            'include_global' => 'cashier-chip.features.owner.include_global',
        ]);
    }

    protected function processRenewals(bool $dryRun, int $graceHours): array
    {
        $dueDate = now()->subHours($graceHours);

        $query = Subscription::withoutGlobalScopes();
        $query = (new Subscription)->scopeForOwner($query);

        $subscriptions = $query
            ->whereActive()
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', $dueDate)
            ->with('billable')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions due for renewal.');

            return [
                'renewed' => 0,
                'failed' => 0,
            ];
        }

        $this->info("Found {$subscriptions->count()} subscription(s) due for renewal.");

        $renewed = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            $billable = $subscription->billable;

            if (! $billable) {
                $this->warn("Subscription {$subscription->id} has no billable subject, skipping.");

                continue;
            }

            /** @var Model&BillableContract $billable */
            $this->line("Processing: {$subscription->type} for {$billable->chipEmail()}");

            if ($dryRun) {
                $this->info('  → Would charge: ' . $this->formatAmount($subscription));
                $renewed++;

                continue;
            }

            try {
                $payment = DB::transaction(function () use ($subscription) {
                    $payment = $this->chargeSubscription($subscription);

                    if ($payment && $payment->isSucceeded()) {
                        $subscription->forceFill([
                            'chip_status' => SubscriptionStatus::Active,
                            'next_billing_at' => now()->add(
                                $subscription->billing_interval ?? 'month',
                                $subscription->billing_interval_count ?? 1
                            ),
                            'renewed_at' => now(),
                        ])->save();
                    }

                    return $payment;
                });

                if ($payment && $payment->isSucceeded()) {
                    $this->info('  ✓ Renewed successfully');
                    $renewed++;

                    SubscriptionRenewed::dispatch($subscription, $payment);
                } else {
                    $this->error('  ✗ Payment requires action or is pending');
                    $this->markAsPastDue($subscription);
                    $failed++;
                }
            } catch (Throwable $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
                Log::error('Subscription renewal failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);

                $this->markAsPastDue($subscription);
                $failed++;

                SubscriptionRenewalFailed::dispatch($subscription, $e->getMessage());
            }
        }

        return [
            'renewed' => $renewed,
            'failed' => $failed,
        ];
    }

    protected function chargeSubscription(Subscription $subscription): mixed
    {
        $billable = $subscription->billable;

        if (! $billable) {
            throw new RuntimeException('Subscription has no billable subject');
        }

        /** @var Model&BillableContract $billable */
        $recurringTokenId = $billable->defaultPaymentMethod()?->id();

        if (! $recurringTokenId) {
            throw new RuntimeException('No payment method available for renewal');
        }

        $amount = $subscription->calculateSubscriptionAmount();

        if ($amount <= 0) {
            throw new RuntimeException('Invalid subscription amount');
        }

        return $billable->charge($amount, $recurringTokenId, [
            'product_name' => "Subscription: {$subscription->type}",
            'reference' => "Subscription {$subscription->type} - Renewal {$subscription->next_billing_at?->format('Y-m-d')}",
            'metadata' => [
                'subscription_id' => $subscription->id,
                'subscription_type' => $subscription->type,
                'renewal' => true,
            ],
        ]);
    }

    protected function markAsPastDue(Subscription $subscription): void
    {
        $subscription->forceFill([
            'chip_status' => SubscriptionStatus::PastDue,
            'past_due_at' => now(),
        ])->save();
    }

    protected function formatAmount(Subscription $subscription): string
    {
        $amount = $subscription->calculateSubscriptionAmount();
        $billable = $subscription->billable;
        $currency = $billable instanceof BillableContract ? $billable->preferredCurrency() : 'MYR';

        return Cashier::formatAmount($amount, $currency);
    }
}
