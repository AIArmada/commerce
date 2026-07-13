<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Console;

use AIArmada\CashierChip\Actions\ChargeChipCustomer;
use AIArmada\CashierChip\Actions\ClaimRenewalAttempt;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Events\SubscriptionRenewed;
use AIArmada\CashierChip\Payment\Payment;
use AIArmada\CashierChip\Subscription\RenewalAttempt;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RenewSubscriptionsCommand extends Command
{
    protected $signature = 'cashier-chip:renew-subscriptions
                            {--dry-run : Show what would be renewed without actually charging}
                            {--grace-hours=0 : Hours of grace period before considering subscription due}';

    protected $description = 'Atomically claim and process CHIP subscription renewals';

    public function __construct(private readonly ClaimRenewalAttempt $claimRenewalAttempt)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $graceHours = max(0, (int) $this->option('grace-hours'));
        $this->info('Processing CHIP subscription renewals...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No claims or charges will be made');
        }

        /** @var array{renewed:int,failed:int,unknown:int,skipped:int}|null $result */
        $result = $this->batchRunner()->run(fn (): array => $this->processRenewals($dryRun, $graceHours));
        $result ??= ['renewed' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0];

        $this->info(sprintf(
            'Renewal complete: %d renewed, %d failed, %d unknown, %d skipped.',
            $result['renewed'],
            $result['failed'],
            $result['unknown'],
            $result['skipped'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function batchRunner(): OwnerBatchRunner
    {
        return new OwnerBatchRunner(Subscription::class, [
            'enabled' => 'cashier-chip.features.owner.enabled',
            'include_global' => 'cashier-chip.features.owner.include_global',
        ]);
    }

    /** @return array{renewed:int,failed:int,unknown:int,skipped:int} */
    protected function processRenewals(bool $dryRun, int $graceHours): array
    {
        $summary = ['renewed' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0];
        $query = Subscription::withoutGlobalScopes();
        $query = (new Subscription)->scopeForOwner($query);
        $query->whereActive()
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', now()->subHours($graceHours))
            ->select('id')
            ->orderBy('id')
            ->chunkById(max(1, (int) config('cashier-chip.renewals.chunk_size', 100)), function ($subscriptions) use ($dryRun, &$summary): void {
                foreach ($subscriptions as $subscription) {
                    if ($dryRun) {
                        $summary['renewed']++;
                        $this->line("Would claim renewal for subscription {$subscription->id}");

                        continue;
                    }

                    $attempt = $this->claimRenewalAttempt->handle((string) $subscription->id);

                    if (! $attempt instanceof RenewalAttempt) {
                        $summary['skipped']++;

                        continue;
                    }

                    $outcome = $this->executeAttempt($attempt);
                    $summary[$outcome]++;
                }
            }, 'id');

        return $summary;
    }

    protected function executeAttempt(RenewalAttempt $attempt): string
    {
        $subscription = $attempt->subscription()->with('billable')->first();
        $billable = $subscription?->billable;

        if (! $subscription instanceof Subscription || ! $billable instanceof Model || ! $billable instanceof BillableContract) {
            $this->recordFailure($attempt, $subscription, 'INVALID_RENEWAL_SUBJECT');

            return 'failed';
        }

        if ($attempt->amount_minor <= 0) {
            $this->recordFailure($attempt, $subscription, 'INVALID_RENEWAL_AMOUNT');

            return 'failed';
        }

        $paymentMethodId = $billable->defaultPaymentMethod()?->id();

        if ($paymentMethodId === null) {
            $this->recordFailure($attempt, $subscription, 'MISSING_PAYMENT_METHOD');

            return 'failed';
        }

        try {
            $payment = $attempt->purchase_id !== null
                ? new Payment(Cashier::chip()->getPurchase($attempt->purchase_id))
                : ChargeChipCustomer::run(
                    $billable,
                    $attempt->amount_minor,
                    $paymentMethodId,
                    [
                        'idempotency_key' => $attempt->id,
                        'product_name' => "Subscription: {$subscription->type}",
                        'reference' => "Renewal {$attempt->id}",
                        'metadata' => [
                            'subscription_id' => $subscription->id,
                            'renewal_attempt_id' => $attempt->id,
                            'renewal_period' => $attempt->period_key,
                        ],
                    ],
                );

            if ($payment->isSucceeded()) {
                $this->recordSuccess($attempt, $subscription, $payment);

                return 'renewed';
            }

            if ($payment->isPending()) {
                $this->recordUnknown($attempt, $payment->id(), 'PAYMENT_PENDING');

                return 'unknown';
            }

            $this->recordFailure($attempt, $subscription, 'PAYMENT_DECLINED');

            return 'failed';
        } catch (Throwable $throwable) {
            Log::warning('CHIP renewal outcome requires reconciliation.', [
                'renewal_attempt_id' => $attempt->id,
                'subscription_id' => $subscription->id,
                'exception' => $throwable::class,
            ]);
            $this->recordUnknown($attempt, null, 'TRANSPORT_OUTCOME_UNKNOWN');

            return 'unknown';
        }
    }

    protected function formatAmount(Subscription $subscription): string
    {
        $billable = $subscription->billable;
        $currency = $billable instanceof BillableContract ? $billable->preferredCurrency() : 'MYR';

        return Cashier::formatAmount($subscription->calculateSubscriptionAmount(), $currency);
    }

    private function recordSuccess(RenewalAttempt $attempt, Subscription $subscription, Payment $payment): void
    {
        $changed = DB::transaction(function () use ($attempt, $subscription, $payment): bool {
            $lockedAttempt = RenewalAttempt::query()->lockForUpdate()->find($attempt->id);
            $lockedSubscription = Subscription::query()->withoutGlobalScopes()->lockForUpdate()->find($subscription->id);

            if (! $lockedAttempt instanceof RenewalAttempt || ! $lockedSubscription instanceof Subscription || $lockedAttempt->status !== 'claimed') {
                return false;
            }

            $nextBillingAt = $lockedSubscription->next_billing_at?->copy()->add(
                $lockedSubscription->billing_interval ?? 'month',
                $lockedSubscription->billing_interval_count ?? 1,
            );
            $lockedSubscription->forceFill([
                'chip_status' => SubscriptionStatus::Active,
                'next_billing_at' => $nextBillingAt,
                'renewed_at' => now(),
                'past_due_at' => null,
            ])->save();
            $lockedAttempt->forceFill([
                'status' => 'completed',
                'purchase_id' => $payment->id(),
                'last_error_code' => null,
                'lease_expires_at' => null,
                'completed_at' => now(),
            ])->save();

            return true;
        }, attempts: 3);

        if ($changed) {
            SubscriptionRenewed::dispatch($subscription->fresh(), $payment);
        }
    }

    private function recordUnknown(RenewalAttempt $attempt, ?string $purchaseId, string $code): void
    {
        RenewalAttempt::query()->whereKey($attempt->id)->where('status', 'claimed')->update([
            'status' => 'unknown',
            'purchase_id' => $purchaseId,
            'last_error_code' => $code,
            'lease_expires_at' => null,
            'updated_at' => now(),
        ]);
    }

    private function recordFailure(RenewalAttempt $attempt, ?Subscription $subscription, string $code): void
    {
        $changed = DB::transaction(function () use ($attempt, $subscription, $code): bool {
            $lockedAttempt = RenewalAttempt::query()->lockForUpdate()->find($attempt->id);

            if (! $lockedAttempt instanceof RenewalAttempt || $lockedAttempt->status !== 'claimed') {
                return false;
            }

            $lockedAttempt->forceFill([
                'status' => 'failed',
                'last_error_code' => $code,
                'lease_expires_at' => null,
                'completed_at' => now(),
            ])->save();

            if ($subscription instanceof Subscription) {
                Subscription::query()->withoutGlobalScopes()->whereKey($subscription->id)->update([
                    'chip_status' => SubscriptionStatus::PastDue,
                    'past_due_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return true;
        }, attempts: 3);

        if ($changed && $subscription instanceof Subscription) {
            SubscriptionRenewalFailed::dispatch($subscription->fresh(), $code);
        }
    }
}
