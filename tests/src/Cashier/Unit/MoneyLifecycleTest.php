<?php

declare(strict_types=1);

use AIArmada\Cashier\Cashier;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Events\PaymentSucceeded;
use AIArmada\Cashier\Events\SubscriptionCreated;
use AIArmada\Cashier\Gateways\Chip\ChipCheckoutBuilder;
use AIArmada\Cashier\Gateways\Chip\ChipPayment;
use AIArmada\Cashier\Gateways\Chip\ChipSubscription;
use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\Cashier\Gateways\StripeGateway;
use AIArmada\Cashier\Support\OwnerScopedQuery;
use AIArmada\Cashier\Support\SnapshotPayment;
use AIArmada\Cashier\Support\SnapshotSubscription;
use AIArmada\Cashier\Support\UnifiedInvoice;
use AIArmada\CashierChip\Subscription\Subscription as ChipSubscriptionModel;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Stripe\Invoice;

uses(CashierTestCase::class);

describe('Money lifecycle guards', function (): void {
    it('refuses bare CHIP price names instead of posting zero amounts', function (): void {
        $builder = new ChipCheckoutBuilder(new ChipGateway([]));

        expect(fn () => $builder->price('Standard Plan'))->toThrow(InvalidArgumentException::class);
    });

    it('parses explicit CHIP name-and-amount prices', function (): void {
        $builder = new ChipCheckoutBuilder(new ChipGateway([]));
        $builder->price('Standard Plan:2500', 2);

        $property = new ReflectionProperty(ChipCheckoutBuilder::class, 'products');
        $property->setAccessible(true);
        $products = $property->getValue($builder);

        expect($products)->toBe([['name' => 'Standard Plan', 'quantity' => 2, 'price' => 2500]]);
    });

    it('rejects non-positive refund amounts without touching gateways', function (): void {
        expect(fn () => (new StripeGateway([]))->refund('pi_test', 0))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => (new ChipGateway([]))->refund('purchase_test', -5))
            ->toThrow(InvalidArgumentException::class);
    });

    it('omits the amount key for full Stripe refunds', function (): void {
        $http = new class implements ClientInterface
        {
            public array $captured = [];

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                $this->captured[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];

                if (str_contains((string) $absUrl, '/v1/refunds')) {
                    return ['{"id":"re_test"}', 200, []];
                }

                return ['{"id":"pi_test","object":"payment_intent","amount":5000,"currency":"usd","status":"succeeded"}', 200, []];
            }
        };

        ApiRequestor::setHttpClient($http);
        config()->set('cashier.secret', 'sk_test_fake');

        try {
            (new StripeGateway([]))->refund('pi_test');

            $refundCall = collect($http->captured)->firstWhere(fn ($call) => str_contains($call['url'], '/v1/refunds'));

            expect($refundCall['params'])->toBe(['payment_intent' => 'pi_test']);
        } finally {
            ApiRequestor::setHttpClient(CurlClient::instance());
        }
    });

    it('threads the locale into money formatting without changing default output', function (): void {
        Cashier::formatCurrencyUsing(null);

        expect(Cashier::formatAmount(1000, 'MYR', 'de_DE'))->toBe('RM10.00')
            ->and(Money::getLocale())->toBe('de_DE');
    });

    it('reads the contract currency for CHIP invoices', function (): void {
        $invoice = UnifiedInvoice::fromChip((object) [
            'id' => 'purchase_test',
            'reference' => 'INV-1',
            'amount' => 7500,
            'currency' => 'usd',
            'status' => 'paid',
        ], 'user_1');

        expect($invoice->currency)->toBe('USD');
    });

    it('reads the Stripe PDF url without calling missing helpers', function (): void {
        $user = $this->createUser(['stripe_id' => 'cus_test']);

        $stripeInvoice = Invoice::constructFrom([
            'id' => 'in_test',
            'customer' => 'cus_test',
            'number' => 'INV-1',
            'currency' => 'usd',
            'total' => 5000,
            'status' => 'open',
            'created' => 1704067200,
            'invoice_pdf' => 'https://pay.stripe.com/invoice/in_test/pdf',
        ]);

        $invoice = UnifiedInvoice::fromStripe(new Laravel\Cashier\Invoice($user, $stripeInvoice), 'user_1');

        expect($invoice->pdfUrl)->toBe('https://pay.stripe.com/invoice/in_test/pdf')
            ->and($invoice->currency)->toBe('USD');
    });
});

describe('Credential and queue hygiene', function (): void {
    it('keeps recurring tokens out of payment serialization', function (): void {
        $purchase = PurchaseData::from([
            'id' => 'purchase_token_test',
            'created_on' => 1704067200,
            'updated_on' => 1704070800,
            'client' => ['email' => 'buyer@example.com'],
            'client_id' => 'chip_cus_test',
            'purchase' => [
                'currency' => 'MYR',
                'total' => 7500,
                'products' => [[
                    'name' => 'Item',
                    'price' => 7500,
                    'quantity' => 1,
                    'discount' => 0,
                    'tax_percent' => 0.0,
                ]],
            ],
            'brand_id' => 'brand_123',
            'status' => 'paid',
            'recurring_token' => 'tok_secret_reusable',
        ]);

        $payment = new ChipPayment($purchase);

        expect($payment->toArray())->not->toHaveKey('recurring_token')
            ->and($payment->toJson())->not->toContain('tok_secret_reusable')
            ->and($payment->recurringToken())->toBe('tok_secret_reusable');
    });

    it('keeps recurring tokens out of subscription serialization', function (): void {
        $model = (new ChipSubscriptionModel)->forceFill([
            'id' => 'sub_test',
            'type' => 'default',
            'chip_id' => 'chip_sub_test',
            'quantity' => 1,
            'recurring_token' => 'tok_secret_reusable',
        ]);

        $subscription = new ChipSubscription($model);

        expect($subscription->toArray())->not->toHaveKey('recurring_token')
            ->and($subscription->toJson())->not->toContain('tok_secret_reusable')
            ->and($subscription->recurringToken())->toBe('tok_secret_reusable');
    });

    it('queues payment events as scalar snapshots', function (): void {
        $payment = Mockery::mock(PaymentContract::class);
        $payment->shouldReceive('id')->andReturn('pi_test');
        $payment->shouldReceive('gateway')->andReturn('stripe');
        $payment->shouldReceive('rawAmount')->andReturn(5000);
        $payment->shouldReceive('amount')->andReturn('$50.00');
        $payment->shouldReceive('currency')->andReturn('USD');
        $payment->shouldReceive('status')->andReturn('succeeded');
        $payment->shouldReceive('errorCode')->andReturnNull();
        $payment->shouldReceive('isPending')->andReturnFalse();
        $payment->shouldReceive('isSucceeded')->andReturnTrue();
        $payment->shouldReceive('isFailed')->andReturnFalse();
        $payment->shouldReceive('isCanceled')->andReturnFalse();
        $payment->shouldReceive('isRefunded')->andReturnFalse();
        $payment->shouldReceive('requiresAction')->andReturnFalse();
        $payment->shouldReceive('requiresRedirect')->andReturnFalse();
        $payment->shouldReceive('redirectUrl')->andReturnNull();
        $payment->shouldReceive('receiptUrl')->andReturn('https://pay.stripe.com/receipt');
        $payment->shouldNotReceive('metadata');

        $user = $this->createUser();
        $restored = unserialize(serialize(new PaymentSucceeded($payment, 'stripe', $user)));

        expect($restored->payment())->toBeInstanceOf(SnapshotPayment::class)
            ->and($restored->payment()->id())->toBe('pi_test')
            ->and($restored->payment()->rawAmount())->toBe(5000)
            ->and($restored->payment()->metadata())->toBe([])
            ->and($restored->billable()->is($user))->toBeTrue();
    });

    it('queues subscription events as scalar snapshots', function (): void {
        $user = $this->createUser();

        $subscription = Mockery::mock(SubscriptionContract::class);
        $subscription->shouldReceive('id')->andReturn('sub_test');
        $subscription->shouldReceive('gateway')->andReturn('chip');
        $subscription->shouldReceive('gatewayId')->andReturn('chip_sub_test');
        $subscription->shouldReceive('type')->andReturn('default');
        $subscription->shouldReceive('active')->andReturnTrue();
        $subscription->shouldReceive('valid')->andReturnTrue();
        $subscription->shouldReceive('onTrial')->andReturnFalse();
        $subscription->shouldReceive('hasExpiredTrial')->andReturnFalse();
        $subscription->shouldReceive('canceled')->andReturnFalse();
        $subscription->shouldReceive('onGracePeriod')->andReturnFalse();
        $subscription->shouldReceive('ended')->andReturnFalse();
        $subscription->shouldReceive('recurring')->andReturnTrue();
        $subscription->shouldReceive('pastDue')->andReturnFalse();
        $subscription->shouldReceive('incomplete')->andReturnFalse();
        $subscription->shouldReceive('hasIncompletePayment')->andReturnFalse();
        $subscription->shouldReceive('quantity')->andReturn(1);
        $subscription->shouldReceive('trialEndsAt')->andReturnNull();
        $subscription->shouldReceive('endsAt')->andReturnNull();
        $subscription->shouldReceive('currentPeriodStart')->andReturnNull();
        $subscription->shouldReceive('currentPeriodEnd')->andReturnNull();

        $restored = unserialize(serialize(new SubscriptionCreated($subscription, $user)));

        expect($restored->subscription())->toBeInstanceOf(SnapshotSubscription::class)
            ->and($restored->subscription()->id())->toBe('sub_test')
            ->and($restored->gateway())->toBe('chip')
            ->and($restored->billable()->is($user))->toBeTrue();
    });

    it('refreshes schema checks after the column cache is flushed', function (): void {
        $table = 'owner_cache_flush_' . bin2hex(random_bytes(4));

        Schema::create($table, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
        });

        $model = new class extends Model {};
        $model->setTable($table);

        $method = new ReflectionMethod(OwnerScopedQuery::class, 'modelHasColumn');
        $method->setAccessible(true);

        try {
            expect($method->invoke(null, $model, 'user_id'))->toBeTrue();

            Schema::drop($table);

            // Still cached without a flush.
            expect($method->invoke(null, $model, 'user_id'))->toBeTrue();

            OwnerScopedQuery::flushColumnCache();

            expect($method->invoke(null, $model, 'user_id'))->toBeFalse();
        } finally {
            Schema::dropIfExists($table);
            OwnerScopedQuery::flushColumnCache();
        }
    });
});
