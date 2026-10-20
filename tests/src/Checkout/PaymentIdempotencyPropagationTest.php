<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\Cashier\Gateways\StripeGateway;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Integrations\Payment\CashierProcessor;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\PaymentAttemptManager;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Checkout\Support\CheckoutPaymentReference;
use Illuminate\Database\Eloquent\Model;

use function Pest\Laravel\mock;

/*
|--------------------------------------------------------------------------
| CHK-06: carry the frozen idempotency identity to the gateway options
|--------------------------------------------------------------------------
|
| The attempt's frozen idempotency key must reach the gateway as the real
| top-level `idempotency_key` charge option that the provider adapters
| consume, not only as attribution metadata. These regressions drive the
| real `CashierProcessor` through the real `ProcessPaymentStep` claim chain
| and capture the options the concrete CHIP and Stripe gateways receive.
|
*/

beforeEach(function (): void {
    Model::clearBootedModels();
});

final class Chk06FakePayment implements PaymentContract
{
    public function __construct(
        private readonly string $id,
        private readonly ?string $redirectUrl = null,
        private readonly string $status = 'pending',
        private readonly int $amount = 0,
        private readonly string $currency = 'MYR',
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function gateway(): string
    {
        return 'chip';
    }

    public function rawAmount(): int
    {
        return $this->amount;
    }

    public function amount(): string
    {
        return number_format($this->amount / 100, 2, '.', '');
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function status(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return [];
    }

    public function errorCode(): ?string
    {
        return null;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSucceeded(): bool
    {
        return in_array($this->status, ['succeeded', 'completed', 'paid'], true);
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'error'], true);
    }

    public function isCanceled(): bool
    {
        return in_array($this->status, ['cancelled', 'canceled'], true);
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function requiresAction(): bool
    {
        return false;
    }

    public function requiresRedirect(): bool
    {
        return $this->redirectUrl !== null;
    }

    public function redirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    public function receiptUrl(): ?string
    {
        return null;
    }

    public function validate(): self
    {
        return $this;
    }

    public function asGatewayPayment(): mixed
    {
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'message' => $this->status === 'failed' ? 'Declined by provider' : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }
}

/**
 * Real CHIP gateway with the transport charge captured instead of posted.
 */
final class Chk06CapturingChipGateway extends ChipGateway
{
    /** @var list<array{billable: BillableContract, amount: int, payment_method: ?string, options: array<string, mixed>}> */
    public array $charges = [];

    /** @var list<PaymentContract> */
    private array $results = [];

    public function queueResult(PaymentContract $result): void
    {
        $this->results[] = $result;
    }

    public function charge(BillableContract $billable, int $amount, ?string $paymentMethod = null, array $options = []): PaymentContract
    {
        $this->charges[] = [
            'billable' => $billable,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'options' => $options,
        ];

        return array_shift($this->results) ?? new Chk06FakePayment('pay_unexpected', null, 'failed');
    }
}

/**
 * Real Stripe gateway with the transport charge captured instead of posted.
 */
final class Chk06CapturingStripeGateway extends StripeGateway
{
    /** @var list<array{billable: BillableContract, amount: int, payment_method: ?string, options: array<string, mixed>}> */
    public array $charges = [];

    /** @var list<PaymentContract> */
    private array $results = [];

    public function queueResult(PaymentContract $result): void
    {
        $this->results[] = $result;
    }

    public function charge(BillableContract $billable, int $amount, ?string $paymentMethod = null, array $options = []): PaymentContract
    {
        $this->charges[] = [
            'billable' => $billable,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'options' => $options,
        ];

        return array_shift($this->results) ?? new Chk06FakePayment('pay_unexpected', null, 'failed');
    }
}

/**
 * Gateway manager wiring the capturing gateways into the real processor.
 */
final class Chk06GatewayManager extends GatewayManager
{
    /** @var array<string, GatewayContract> */
    public array $gateways = [];

    /** @var list<string|null> */
    public array $requested = [];

    public function gateway(?string $name = null): GatewayContract
    {
        $this->requested[] = $name;

        $resolved = $name ?? $this->getDefaultDriver();

        if (! isset($this->gateways[$resolved])) {
            throw new RuntimeException("No capturing gateway is wired for [{$resolved}].");
        }

        return $this->gateways[$resolved];
    }
}

/**
 * Checkout session whose pinned relations survive `refresh()`.
 *
 * `PaymentAttemptManager` re-reads the session row under the lock, which
 * reloads relations. The real flow resolves the billable from the persisted
 * morph columns; tests pin the mock billable so it survives that refresh.
 */
final class Chk06Session extends CheckoutSession
{
    /** @var array<string, mixed> */
    private array $pinnedRelations = [];

    public function pinRelation(string $name, mixed $value): self
    {
        $this->pinnedRelations[$name] = $value;
        $this->setRelation($name, $value);

        return $this;
    }

    public function refresh()
    {
        parent::refresh();

        foreach ($this->pinnedRelations as $name => $value) {
            $this->setRelation($name, $value);
        }

        return $this;
    }
}

function chk06Session(string $cartId, string $provider, int $total = 2500): Chk06Session
{
    $session = Chk06Session::forceCreate([
        'cart_id' => $cartId,
        'status' => Pending::class,
        'selected_payment_gateway' => 'cashier',
        'grand_total' => $total,
        'currency' => 'MYR',
        'payment_data' => [
            'provider' => $provider,
            'payment_method' => null,
        ],
    ]);

    return $session->pinRelation('billable', chk06Billable());
}

function chk06Reload(Chk06Session $session): Chk06Session
{
    $billable = $session->billable;

    return $session->fresh()->pinRelation('billable', $billable);
}

function chk06Step(?PaymentAttemptManager $attemptManager = null): ProcessPaymentStep
{
    $resolver = new PaymentGatewayResolver(defaultGateway: 'cashier');
    $resolver->register('cashier', new CashierProcessor);

    return new ProcessPaymentStep($resolver, $attemptManager ?? app(PaymentAttemptManager::class));
}

function chk06Billable(): BillableContract
{
    return mock(BillableContract::class);
}

it('drains the frozen attempt key into the CHIP gateway options with metadata attribution', function (): void {
    $session = chk06Session('chk06-chip-options', 'chip');

    $gateway = new Chk06CapturingChipGateway;
    $gateway->queueResult(new Chk06FakePayment('pay_chip_1', 'https://gateway.example.test/pay/1', 'pending', 2500));

    $manager = new Chk06GatewayManager(app());
    $manager->gateways['chip'] = $gateway;
    app()->instance(GatewayManager::class, $manager);

    $result = chk06Step()->handle($session);

    $expectedKey = $session->getKey() . ':attempt:1';

    expect($result->isSuccessful())->toBeTrue()
        ->and($manager->requested)->toBe(['chip'])
        ->and($gateway->charges)->toHaveCount(1);

    $charge = $gateway->charges[0];
    $options = $charge['options'];

    expect($charge['amount'])->toBe(2500)
        ->and($options['idempotency_key'])->toBe($expectedKey)
        ->and($options['metadata']['checkout_idempotency_key'])->toBe($expectedKey)
        ->and($options['metadata']['checkout_session_id'])->toBe(CheckoutPaymentReference::forSession($session))
        ->and($options['metadata']['checkout_gateway'])->toBe('cashier')
        ->and($options['metadata']['checkout_attempt'])->toBe(1)
        ->and($options['reference'])->toBe(CheckoutPaymentReference::forSession($session))
        ->and($options['currency'])->toBe('MYR');

    $fresh = $session->fresh();

    expect($fresh->status)->toBeInstanceOf(AwaitingPayment::class)
        ->and($fresh->payment_id)->toBe('pay_chip_1')
        ->and(data_get($fresh->payment_data, 'payment_attempt.idempotency_key'))->toBe($expectedKey);
});

it('redrives one attempt with the same key and never posts a second charge', function (): void {
    $session = chk06Session('chk06-chip-redrive', 'chip');

    $gateway = new Chk06CapturingChipGateway;
    $gateway->queueResult(new Chk06FakePayment('pay_chip_redrive', 'https://gateway.example.test/pay/redrive', 'pending', 2500));

    $manager = new Chk06GatewayManager(app());
    $manager->gateways['chip'] = $gateway;
    app()->instance(GatewayManager::class, $manager);

    $step = chk06Step();

    $first = $step->handle($session);
    $second = $step->handle(chk06Reload($session));

    expect($first->isSuccessful())->toBeTrue()
        ->and($second->isObserved())->toBeTrue()
        ->and($gateway->charges)->toHaveCount(1)
        ->and($gateway->charges[0]['options']['idempotency_key'])->toBe($session->getKey() . ':attempt:1')
        ->and($session->fresh()->payment_id)->toBe('pay_chip_redrive');
});

it('issues a new key for a definitively failed attempt and a new charge only then', function (): void {
    $session = chk06Session('chk06-chip-retry', 'chip');

    $gateway = new Chk06CapturingChipGateway;
    $gateway->queueResult(new Chk06FakePayment('pay_chip_declined', null, 'failed', 2500));
    $gateway->queueResult(new Chk06FakePayment('pay_chip_retry', 'https://gateway.example.test/pay/retry', 'pending', 2500));

    $manager = new Chk06GatewayManager(app());
    $manager->gateways['chip'] = $gateway;
    app()->instance(GatewayManager::class, $manager);

    $step = chk06Step();

    $declined = $step->handle($session);
    $retried = $step->handle(chk06Reload($session));

    expect($declined->status)->toBe(StepStatus::Failed)
        ->and($retried->isSuccessful())->toBeTrue()
        ->and($gateway->charges)->toHaveCount(2);

    $firstKey = $gateway->charges[0]['options']['idempotency_key'];
    $secondKey = $gateway->charges[1]['options']['idempotency_key'];

    expect($firstKey)->toBe($session->getKey() . ':attempt:1')
        ->and($secondKey)->toBe($session->getKey() . ':attempt:2')
        ->and($secondKey)->not->toBe($firstKey)
        ->and($gateway->charges[0]['options']['metadata']['checkout_attempt'])->toBe(1)
        ->and($gateway->charges[1]['options']['metadata']['checkout_attempt'])->toBe(2);

    $fresh = $session->fresh();

    expect($fresh->payment_attempts)->toBe(2)
        ->and($fresh->payment_id)->toBe('pay_chip_retry')
        ->and(data_get($fresh->payment_data, 'payment_attempt.idempotency_key'))->toBe($secondKey);
});

it('forwards the explicit frozen key for a Stripe charge and keeps metadata attribution separate', function (): void {
    $session = chk06Session('chk06-stripe-options', 'stripe');

    $gateway = new Chk06CapturingStripeGateway;
    $gateway->queueResult(new Chk06FakePayment('pi_chk06', null, 'succeeded', 2500));

    $manager = new Chk06GatewayManager(app());
    $manager->gateways['stripe'] = $gateway;
    app()->instance(GatewayManager::class, $manager);

    $result = app(CashierProcessor::class)->createPayment($session, new PaymentRequest(
        amount: 2500,
        currency: 'MYR',
        gateway: 'cashier',
        description: 'Checkout session',
        successUrl: 'https://example.test/success',
        failureUrl: 'https://example.test/failure',
        cancelUrl: 'https://example.test/cancel',
        metadata: [
            'checkout_idempotency_key' => 'chk06-metadata-only-key',
            'checkout_attempt' => 4,
        ],
        paymentMethod: 'pm_card_visa',
        provider: 'stripe',
        idempotencyKey: 'chk06-explicit-stripe-key',
    ));

    expect($result->status)->toBe(PaymentStatus::Completed)
        ->and($result->paymentId)->toBe('pi_chk06')
        ->and($manager->requested)->toBe(['stripe'])
        ->and($gateway->charges)->toHaveCount(1);

    $options = $gateway->charges[0]['options'];

    expect($options['idempotency_key'])->toBe('chk06-explicit-stripe-key')
        ->and($options['metadata']['checkout_idempotency_key'])->toBe('chk06-metadata-only-key')
        ->and($options['metadata']['checkout_attempt'])->toBe(4)
        ->and($gateway->charges[0]['payment_method'])->toBe('pm_card_visa');
});
