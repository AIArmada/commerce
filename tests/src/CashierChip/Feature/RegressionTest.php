<?php

declare(strict_types=1);

use AIArmada\CashierChip\Actions\ClaimRenewalAttempt;
use AIArmada\CashierChip\Actions\SyncChipPurchaseStatus;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Billing\Checkout;
use AIArmada\CashierChip\Console\RenewSubscriptionsCommand;
use AIArmada\CashierChip\Contracts\InvoiceRenderer;
use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Exceptions\InvalidCoupon;
use AIArmada\CashierChip\Exceptions\InvalidCustomer;
use AIArmada\CashierChip\Invoice\Invoice;
use AIArmada\CashierChip\Subscription\RenewalAttempt;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\States\Paused;
use Akaunting\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(CashierChipTestCase::class);

final class RepairFakeVoucherService extends VoucherService
{
    /** @param array<string, VoucherData> $vouchersByCode */
    public function __construct(private array $vouchersByCode) {}

    public function find(string $code): ?VoucherData
    {
        return $this->vouchersByCode[$code] ?? null;
    }

    /** @param array<string, mixed>|null $metadata */
    public function recordUsage(
        string $code,
        Money $discountAmount,
        ?string $channel = null,
        ?array $metadata = null,
        ?Model $redeemedBy = null,
        ?string $notes = null,
        ?VoucherModel $voucherModel = null,
    ): void {}
}

function regressionVoucher(string $code, string $status, array $metadata = []): VoucherData
{
    return VoucherData::fromArray([
        'id' => 'v_' . $code,
        'code' => $code,
        'name' => $code,
        'type' => VoucherType::Fixed->value,
        'value' => 200,
        'currency' => 'MYR',
        'status' => $status,
        'metadata' => $metadata,
    ]);
}

describe('RepairRegression money lifecycle', function (): void {
    it('invoices tab items without calling undefined builder methods', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_invoice']);

        $user->tab('Item 1', 1000, ['quantity' => 2]);
        $user->tab('Item 2', 500);

        $invoice = $user->invoice();

        expect($invoice)->toBeInstanceOf(Invoice::class)
            ->and($invoice->rawTotal())->toBe(2500)
            ->and($user->tabs)->toBe([]);
    });

    it('swap carries over unit amounts and accepts overrides', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_swap']);
        $subscription = $this->createTrustedSubscription($user, [
            'type' => 'default',
            'chip_id' => 'sub_swap',
            'chip_status' => SubscriptionStatus::Active,
            'chip_price' => 'price_monthly',
            'quantity' => 1,
        ]);
        $this->createTrustedSubscriptionItem($subscription, [
            'chip_id' => 'item_swap',
            'chip_product' => 'prod',
            'chip_price' => 'price_monthly',
            'quantity' => 1,
            'unit_amount' => 1000,
        ]);

        $subscription->swap('price_monthly');

        expect($subscription->fresh()?->calculateSubscriptionAmount())->toBe(1000);

        $subscription->swap(['price_new' => ['unit_amount' => 1500]]);

        expect($subscription->fresh()?->calculateSubscriptionAmount())->toBe(1500);
    });

    it('renewals freeze the discounted amount', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_discount']);
        $subscription = Subscription::factory()->for($user, 'billable')->create([
            'chip_status' => SubscriptionStatus::Active,
            'next_billing_at' => CarbonImmutable::now()->subDay(),
            'coupon_id' => 'FOREVER200',
            'coupon_discount' => 200,
            'coupon_duration' => 'forever',
            'coupon_applied_at' => CarbonImmutable::now()->subDay(),
        ]);
        SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => 1000,
            'quantity' => 1,
        ]);

        $subscription->refresh();

        expect($subscription->renewalAmount())->toBe(800)
            ->and($subscription->upcomingInvoice()?->rawTotal())->toBe(800);

        $attempt = app(ClaimRenewalAttempt::class)->handle($subscription->id);

        expect($attempt?->amount_minor)->toBe(800);
    });

    it('checkout rejects out-of-bounds amounts and normalizes currency', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_checkout']);

        expect(fn () => Checkout::create($user, 0))->toThrow(InvalidArgumentException::class);
        expect(fn () => Checkout::create($user, -50))->toThrow(InvalidArgumentException::class);

        $checkout = Checkout::create($user, 1000, ['currency' => 'myr']);

        expect($checkout->asChipPurchase()->getCurrency())->toBe('MYR');
    });

    it('charging an orphaned subscription throws InvalidCustomer', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_orphan']);
        $subscription = $this->createTrustedSubscription($user, [
            'type' => 'default',
            'chip_id' => 'sub_orphan',
            'chip_status' => SubscriptionStatus::Active,
        ]);
        $subscription->forceFill(['billable_id' => (string) Str::uuid()])->save();

        expect(fn () => $subscription->fresh()?->charge(1000))->toThrow(InvalidCustomer::class);
    });

    it('rejects unknown billing intervals at the builder and the model', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_interval']);

        expect(fn () => $user->newSubscription('default', 'price_x')->billingInterval('fortnight'))->toThrow(InvalidArgumentException::class);

        $subscription = $this->createTrustedSubscription($user, [
            'type' => 'default',
            'chip_id' => 'sub_interval',
            'chip_status' => SubscriptionStatus::Active,
        ]);

        expect(fn () => $subscription->forceFill(['billing_interval' => 'fortnight'])->save())->toThrow(InvalidArgumentException::class);
    });

    it('subscription() resolves a single type without loading history', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_lookup']);

        foreach (['a', 'b', 'c'] as $type) {
            $this->createTrustedSubscription($user, [
                'type' => $type,
                'chip_id' => 'sub_' . $type,
                'chip_status' => SubscriptionStatus::Active,
            ]);
        }

        DB::enableQueryLog();
        $found = $user->subscription('b');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($found?->type)->toBe('b')
            ->and(count($queries))->toBeLessThanOrEqual(2);
    });

    it('invoices sort newest first by purchase date', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_sort']);
        $subscription = $this->createTrustedSubscription($user, [
            'type' => 'default',
            'chip_id' => 'sub_sort',
            'chip_status' => SubscriptionStatus::Active,
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->subDay());
        $old = $this->fakeChip->createPurchase(['client_id' => 'cli_sort']);
        RenewalAttempt::create([
            'subscription_id' => $subscription->id,
            'status' => 'completed',
            'amount_minor' => 1000,
            'period_key' => 'old',
            'purchase_id' => $old->id,
            'completed_at' => CarbonImmutable::now(),
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addDay());
        $new = $this->fakeChip->createPurchase(['client_id' => 'cli_sort']);
        RenewalAttempt::create([
            'subscription_id' => $subscription->id,
            'status' => 'completed',
            'amount_minor' => 1000,
            'period_key' => 'new',
            'purchase_id' => $new->id,
            'completed_at' => CarbonImmutable::now(),
        ]);
        CarbonImmutable::setTestNow();

        $ids = $user->invoices()->map(fn (Invoice $invoice): string => $invoice->id())->all();

        expect($ids)->toBe([$new->id, $old->id]);
    });
});

describe('RepairRegression renewal idempotency', function (): void {
    it('duplicate paid webhooks extend billing only once', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_dedup']);
        $subscription = $this->createTrustedSubscription($user, [
            'type' => 'standard',
            'chip_id' => 'sub_dedup',
            'chip_status' => SubscriptionStatus::Active,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
            'next_billing_at' => CarbonImmutable::now()->subDay(),
        ]);

        $payload = [
            'id' => 'pur_dedup_1',
            'client_id' => 'cli_dedup',
            'status' => 'paid',
            'purchase' => [
                'total' => 1000,
                'currency' => 'MYR',
                'metadata' => ['subscription_type' => 'standard'],
            ],
        ];
        $purchaseData = PurchaseData::from($payload);

        SyncChipPurchaseStatus::run($user, $purchaseData, $payload);
        $first = $subscription->fresh()?->next_billing_at;

        SyncChipPurchaseStatus::run($user, $purchaseData, $payload);
        $second = $subscription->fresh()?->next_billing_at;

        expect($first)->not->toBeNull()
            ->and($second?->toIso8601String())->toBe($first?->toIso8601String());
    });

    it('paid webhooks never resurrect canceled subscriptions', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_nores']);
        $nextBilling = CarbonImmutable::now()->subDay();
        $subscription = $this->createTrustedSubscription($user, [
            'type' => 'standard',
            'chip_id' => 'sub_nores',
            'chip_status' => SubscriptionStatus::Canceled,
            'ends_at' => CarbonImmutable::now()->subHour(),
            'next_billing_at' => $nextBilling,
        ]);

        $payload = [
            'id' => 'pur_nores_1',
            'client_id' => 'cli_nores',
            'status' => 'paid',
            'purchase' => [
                'total' => 1000,
                'currency' => 'MYR',
                'metadata' => ['subscription_type' => 'standard'],
            ],
        ];

        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        $fresh = $subscription->fresh();

        expect($fresh?->chip_status)->toBe(SubscriptionStatus::Canceled)
            ->and($fresh?->next_billing_at?->toIso8601String())->toBe($nextBilling->toIso8601String());
    });

    it('renewal charges carry a stable idempotency key', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_idem']);
        $token = $this->fakeChip->getFakeClient()->addRecurringToken($user->chip_id);
        $user->updateDefaultPaymentMethod($token['id']);

        $subscription = Subscription::factory()->for($user, 'billable')->create([
            'chip_status' => SubscriptionStatus::Active,
            'next_billing_at' => CarbonImmutable::now()->subDay(),
        ]);
        SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => 1000,
            'quantity' => 1,
        ]);

        $command = app(RenewSubscriptionsCommand::class);
        $method = new ReflectionMethod($command, 'processRenewals');
        $method->setAccessible(true);
        $result = $method->invoke($command, false, 0);

        $purchases = array_values($this->fakeChip->getFakeClient()->getPurchases());
        $attempt = RenewalAttempt::query()->where('subscription_id', $subscription->id)->first();

        expect($result['renewed'])->toBe(1)
            ->and($purchases)->toHaveCount(1)
            ->and($purchases[0]['idempotency_key'] ?? null)->toBe('renewal-' . $attempt?->id);
    });

    it('unknown attempts with a paid purchase reconcile instead of recharging', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_recon']);
        $subscription = Subscription::factory()->for($user, 'billable')->create([
            'chip_status' => SubscriptionStatus::Active,
            'next_billing_at' => CarbonImmutable::now()->subDay(),
        ]);
        SubscriptionItem::factory()->forSubscription($subscription)->create([
            'unit_amount' => 1000,
            'quantity' => 1,
        ]);

        $purchase = $this->fakeChip->createPurchase(['client_id' => 'cli_recon']);
        $this->fakeChip->simulatePaymentComplete($purchase->id);

        $unknown = RenewalAttempt::create([
            'subscription_id' => $subscription->id,
            'status' => 'unknown',
            'amount_minor' => 1000,
            'period_key' => ClaimRenewalAttempt::periodKeyFor($subscription),
            'purchase_id' => $purchase->id,
            'last_error_code' => 'TRANSPORT_OUTCOME_UNKNOWN',
        ]);

        $command = app(RenewSubscriptionsCommand::class);
        $method = new ReflectionMethod($command, 'processRenewals');
        $method->setAccessible(true);
        $result = $method->invoke($command, false, 0);

        expect($result['renewed'])->toBe(1)
            ->and($unknown->fresh()?->status)->toBe('completed')
            ->and($this->fakeChip->getFakeClient()->getPurchases())->toHaveCount(1);
    });
});

describe('RepairRegression security', function (): void {
    it('rejects expired coupons at subscription creation', function (): void {
        $this->app->instance(VoucherService::class, new RepairFakeVoucherService([
            'DEAD' => regressionVoucher('DEAD', Paused::class, ['duration' => 'once']),
        ]));

        $user = $this->createUser(['chip_id' => 'cli_coupon']);

        expect(fn () => $user->newSubscription('default', 'price_x')->withCoupon('DEAD')->create())
            ->toThrow(InvalidCoupon::class);
    });

    it('recurring tokens are encrypted at rest', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_encrypt']);

        $stored = Cashier::paymentMethodStore()->saveForBillable($user, 'tok_secret_123', ['type' => 'card'], true);

        $raw = DB::table($stored->getTable())->where('id', $stored->id)->value('recurring_token');

        expect($raw)->not->toBe('tok_secret_123')
            ->and($stored->fresh()?->recurring_token)->toBe('tok_secret_123')
            ->and(Cashier::paymentMethodStore()->findForBillable($user, 'tok_secret_123')?->id)->toBe($stored->id);
    });

    it('findPayment hides purchases owned by other billables', function (): void {
        $owner = $this->createUser();
        $owner->createAsChipCustomer();
        $stranger = $this->createUser();
        $stranger->createAsChipCustomer();

        $payment = $owner->charge(1000);

        expect($owner->findPayment($payment->id())?->id())->toBe($payment->id())
            ->and($stranger->findPayment($payment->id()))->toBeNull();
    });

    it('invoice downloads sanitize attacker-influenced references', function (): void {
        $this->app->instance(InvoiceRenderer::class, new class implements InvoiceRenderer
        {
            public function render(Invoice $invoice, array $data = [], array $options = []): string
            {
                return 'pdf-bytes';
            }

            public function paperSize(): string
            {
                return 'A4';
            }
        });

        $user = $this->createUser(['chip_id' => 'cli_filename']);
        $purchase = PurchaseData::from([
            'id' => 'pur_filename_1',
            'status' => 'paid',
            'reference' => "x\";\r\nEvil: 1",
        ]);

        $response = (new Invoice($user, $purchase))->download();
        $header = $response->headers->get('Content-Disposition') ?? '';

        expect($header)->not->toContain("\r")
            ->and($header)->not->toContain("\n")
            ->and($header)->toContain('filename="invoice-x-Evil-1.pdf"');
    });

    it('setup purchases ignore client and brand overrides', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_setup']);

        $purchase = $user->createSetupPurchase([
            'idempotency_key' => 'setup-repair-1',
            'chip' => [
                'client_id' => 'cli_evil',
                'brand_id' => 'brand_evil',
                'purchase' => ['total_override' => 999999],
                'success_redirect' => 'https://example.com/ok',
            ],
        ]);

        $stored = $this->fakeChip->getFakeClient()->getPurchase($purchase->id);

        expect($purchase->client_id)->toBe('cli_setup')
            ->and($stored['brand_id'] ?? null)->not->toBe('brand_evil')
            ->and($stored['success_redirect'] ?? null)->toBe('https://example.com/ok');
    });

    it('charge URLs must be absolute http(s) and honor the host allowlist', function (): void {
        $user = $this->createUser();
        $user->createAsChipCustomer();

        expect(fn () => $user->charge(1000, null, ['success_url' => 'javascript:alert(1)']))->toThrow(InvalidArgumentException::class);

        config()->set('cashier-chip.redirects.allowed_hosts', ['billing.example.com']);

        expect(fn () => $user->charge(1000, null, ['success_url' => 'https://evil.example.com/x']))->toThrow(InvalidArgumentException::class);

        $payment = $user->charge(1000, null, ['success_url' => 'https://billing.example.com/ok']);

        expect($payment->id())->not->toBe('');
    });

    it('stored method metadata excludes the raw purchase payload', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_meta']);

        $payload = [
            'id' => 'pur_meta_1',
            'client_id' => 'cli_meta',
            'status' => 'paid',
            'recurring_token' => 'tok_meta_1',
            'transaction_data' => [
                'payment_method' => 'visa',
                'extra' => ['masked_pan' => '**** **** **** 4242', 'pii' => 'secret'],
            ],
            'purchase' => ['total' => 1000, 'currency' => 'MYR'],
        ];

        SyncChipPurchaseStatus::run($user, PurchaseData::from($payload), $payload);

        $metadata = Cashier::paymentMethodStore()->findForBillable($user, 'tok_meta_1')?->metadata ?? [];

        expect($metadata)->not->toHaveKey('purchase')
            ->and($metadata)->not->toHaveKey('transaction_data')
            ->and($metadata['purchase_id'] ?? null)->toBe('pur_meta_1');
    });

    it('no implicit default when nothing is flagged', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_nodefault']);

        Cashier::paymentMethodStore()->saveForBillable($user, 'tok_a', [], true);
        Cashier::paymentMethodStore()->saveForBillable($user, 'tok_b', [], false);

        DB::table('cashier_chip_payment_methods')
            ->where('billable_id', $user->getKey())
            ->update(['is_default' => false]);

        expect(Cashier::paymentMethodStore()->defaultForBillable($user))->toBeNull()
            ->and($user->fresh()?->hasDefaultPaymentMethod())->toBeFalse();
    });

    it('isDefault reflects the stored flag', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_isdefault']);

        $first = Cashier::paymentMethodStore()->saveForBillable($user, 'tok_first', [], true);
        $second = Cashier::paymentMethodStore()->saveForBillable($user, 'tok_second', [], false);

        $methods = $user->paymentMethods()->keyBy(fn ($method) => $method->id());

        expect($methods['tok_first']?->isDefault())->toBeTrue()
            ->and($methods['tok_second']?->isDefault())->toBeFalse()
            ->and($first->is_default)->toBeTrue();
    });

    it('recurring_token is not mass assignable on subscriptions', function (): void {
        $subscription = new Subscription(['recurring_token' => 'tok_mass']);

        expect($subscription->recurring_token)->toBeNull();
    });

    it('webhook command reports the chip signature switch', function (): void {
        config()->set('chip.webhooks.verify_signature', false);

        $this->artisan('cashier-chip:webhook')
            ->expectsOutputToContain('Disabled')
            ->assertSuccessful();
    });
});
