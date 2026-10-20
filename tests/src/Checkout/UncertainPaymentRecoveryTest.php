<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Facades\Chip;
use AIArmada\Checkout\Actions\CheckoutFinalizer;
use AIArmada\Checkout\Actions\HandleCheckoutPaymentCallback;
use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\PaymentCompensationInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\CheckoutResult;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Events\CheckoutFailed;
use AIArmada\Checkout\Events\CheckoutStepFailed;
use AIArmada\Checkout\Exceptions\PaymentException;
use AIArmada\Checkout\Integrations\Payment\CashierChipProcessor;
use AIArmada\Checkout\Integrations\Payment\CashierProcessor;
use AIArmada\Checkout\Integrations\Payment\ChipProcessor;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use AIArmada\Checkout\Services\StepExecutor;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\PaymentFailed as PaymentFailedState;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Checkout\Support\CheckoutCallbackStatePolicy;
use AIArmada\Checkout\Support\ChipPaymentStatusMapper;
use AIArmada\Checkout\Support\ChipPurchasePayloadBuilder;
use AIArmada\Checkout\Support\ChipRefundGateway;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\mock;

/*
|--------------------------------------------------------------------------
| CHK-08: attempt outcomes and recovery are authoritative
|--------------------------------------------------------------------------
|
| An uncertain creation response (gateway I/O failed after the provider may
| have accepted the charge) must stay recoverable: the same generation,
| provider, request identity, and idempotency key reconcile it, even at the
| new-charge retry limit, and no failure compensation runs before payment
| truth is known. A verified callback failure must become the attempt's
| durable outcome so a retry can release a new generation when budget
| permits. Known non-terminal outcomes must not be mistaken for failures.
|
*/

beforeEach(function (): void {
    Model::clearBootedModels();
});

function chk08Tracker(): object
{
    return new class
    {
        public int $creates = 0;

        /** @var list<string|null> */
        public array $keys = [];

        /** @var list<int|null> */
        public array $attempts = [];

        /** @var list<string|null> */
        public array $gateways = [];

        /** @var list<string|null> */
        public array $providers = [];

        /** @var list<string> */
        public array $executed = [];

        /** @var list<string> */
        public array $voided = [];

        public ?CheckoutResult $competed = null;
    };
}

function chk08TrackStep(string $identifier, array $dependencies = [], ?object $tracker = null): CheckoutStepInterface
{
    return new class($identifier, $dependencies, $tracker) implements CheckoutStepInterface
    {
        public function __construct(
            private readonly string $identifier,
            private readonly array $dependencies,
            private readonly ?object $tracker,
        ) {}

        public function getIdentifier(): string
        {
            return $this->identifier;
        }

        public function getName(): string
        {
            return $this->identifier;
        }

        public function validate(CheckoutSession $session): array
        {
            return [];
        }

        public function handle(CheckoutSession $session): StepResult
        {
            if ($this->tracker !== null) {
                $this->tracker->executed[] = $this->identifier;
            }

            return StepResult::success($this->identifier);
        }

        public function canSkip(CheckoutSession $session): bool
        {
            return false;
        }

        public function compensate(CheckoutSession $session): StepResult
        {
            return StepResult::compensated($this->identifier);
        }

        public function getDependencies(): array
        {
            return $this->dependencies;
        }
    };
}

function chk08Session(string $cartId, string $gateway = 'chip', int $total = 1000, array $overrides = []): CheckoutSession
{
    return CheckoutSession::forceCreate(array_merge([
        'cart_id' => $cartId,
        'status' => Pending::class,
        'selected_payment_gateway' => $gateway,
        'grand_total' => $total,
        'currency' => 'MYR',
        'step_states' => [
            'calculate_shipping' => 'pending',
            'process_payment' => 'pending',
            'create_order' => 'pending',
        ],
    ], $overrides));
}

/**
 * @param  Closure(CheckoutSession, PaymentRequest): PaymentResult  $onCreate
 */
function chk08Processor(
    Closure $onCreate,
    object $tracker,
    ?PaymentResult $callbackResult = null,
    string $identifier = 'chip',
): PaymentProcessorInterface {
    return new class($onCreate, $tracker, $callbackResult, $identifier) implements PaymentCompensationInterface, PaymentProcessorInterface
    {
        public function __construct(
            private readonly Closure $onCreate,
            private readonly object $tracker,
            private readonly ?PaymentResult $callbackResult,
            private readonly string $identifier,
        ) {}

        public function getIdentifier(): string
        {
            return $this->identifier;
        }

        public function getName(): string
        {
            return 'checkout-chk08-gateway';
        }

        public function isAvailable(CheckoutSession $session): bool
        {
            return true;
        }

        public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
        {
            $this->tracker->creates++;
            $this->tracker->keys[] = $request->metadata['checkout_idempotency_key'] ?? null;
            $this->tracker->attempts[] = $request->metadata['checkout_attempt'] ?? null;
            $this->tracker->gateways[] = $request->gateway;
            $this->tracker->providers[] = $request->provider;

            return ($this->onCreate)($session, $request);
        }

        public function handleCallback(array $payload): PaymentResult
        {
            return $this->callbackResult ?? PaymentResult::failed('unused');
        }

        public function getRedirectUrl(CheckoutSession $session): ?string
        {
            return null;
        }

        public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
        {
            return new PaymentResult(status: PaymentStatus::Refunded, paymentId: $paymentId);
        }

        public function checkStatus(string $paymentId): PaymentResult
        {
            return PaymentResult::failed('unused', [], $paymentId);
        }

        public function voidPayment(string $paymentId, ?string $reason = null): PaymentResult
        {
            $this->tracker->voided[] = $paymentId;

            return new PaymentResult(status: PaymentStatus::Cancelled, paymentId: $paymentId);
        }
    };
}

function chk08Resolver(PaymentProcessorInterface $processor, string $gateway = 'chip'): PaymentGatewayResolver
{
    $resolver = new PaymentGatewayResolver(defaultGateway: $gateway);
    $resolver->register($gateway, $processor);

    return $resolver;
}

function chk08Service(PaymentGatewayResolverInterface $resolver, object $tracker, ?object $holder = null): CheckoutService
{
    $registry = new CheckoutStepRegistry;
    $registry->register('calculate_shipping', chk08TrackStep('calculate_shipping', [], $tracker));
    $registry->register('process_payment', new ProcessPaymentStep($resolver));
    $registry->register('create_order', chk08TrackStep('create_order', ['process_payment'], $tracker));
    $registry->setOrder(['calculate_shipping', 'process_payment', 'create_order']);

    $service = new CheckoutService(
        stepRegistry: $registry,
        events: app(Dispatcher::class),
        stepExecutor: new StepExecutor($registry, app(Dispatcher::class)),
        finalizer: new CheckoutFinalizer(app(Dispatcher::class)),
        paymentResolver: $resolver,
    );

    if ($holder !== null) {
        $holder->service = $service;
    }

    return $service;
}

final class Chk08Session extends CheckoutSession
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

function chk08BillableSession(string $cartId): Chk08Session
{
    $session = Chk08Session::forceCreate([
        'cart_id' => $cartId,
        'status' => Pending::class,
        'selected_payment_gateway' => 'cashier',
        'grand_total' => 1000,
        'currency' => 'MYR',
        'payment_data' => [
            'provider' => 'chip',
            'payment_method' => null,
        ],
        'step_states' => [
            'calculate_shipping' => 'pending',
            'process_payment' => 'pending',
            'create_order' => 'pending',
        ],
    ]);

    return $session->pinRelation('billable', mock(BillableContract::class));
}

function chk08Reload(Chk08Session $session): Chk08Session
{
    $billable = $session->billable;

    return $session->fresh()->pinRelation('billable', $billable);
}

/**
 * Compensation rolls back every completed step, including the pre-payment
 * steps. A retry harness keeps those valid so the retry reaches the payment
 * step, matching the existing retry-limit regression convention.
 */
function chk08KeepPrepStepsValid(CheckoutSession $session): CheckoutSession
{
    $session->setStepState('calculate_shipping', StepStatus::Completed);

    return $session;
}

final class Chk08FakePayment implements PaymentContract
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
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }
}

/**
 * Real CHIP gateway whose transport is captured and can fail once.
 */
final class Chk08CapturingChipGateway extends ChipGateway
{
    /** @var list<array{billable: BillableContract, amount: int, payment_method: ?string, options: array<string, mixed>}> */
    public array $charges = [];

    public bool $failOnFirstCharge = false;

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

        if ($this->failOnFirstCharge && count($this->charges) === 1) {
            throw new RuntimeException('Cashier gateway timed out');
        }

        return array_shift($this->results) ?? new Chk08FakePayment('pay_unexpected', null, 'failed');
    }
}

/**
 * Gateway manager wiring the capturing gateway into the real processor.
 */
final class Chk08GatewayManager extends GatewayManager
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

describe('uncertain attempt outcomes', function (): void {
    it('keeps an uncertain creation response recoverable and reconciles it through the public API', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $tracker = chk08Tracker();

        $processor = chk08Processor(function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
            return $tracker->creates === 1
                ? PaymentResult::uncertain('Gateway timed out')
                : PaymentResult::pending('pay_chk08_recovered', 'https://gateway.example.test/pay/chk08');
        }, $tracker);

        $service = chk08Service(chk08Resolver($processor), $tracker);

        $session = chk08Session('chk08-uncertain-recovery');
        $expectedKey = $session->getKey() . ':attempt:1';

        $first = $service->processCheckout($session);

        expect($first->success)->toBeFalse()
            ->and($first->metadata['processing'] ?? null)->toBeTrue()
            ->and($first->metadata['reconciliation'] ?? null)->toBeTrue();

        $pending = $session->fresh();

        expect($pending->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($pending->payment_attempts)->toBe(1)
            ->and(data_get($pending->payment_data, 'payment_attempt.idempotency_key'))->toBe($expectedKey)
            ->and(data_get($pending->payment_data, 'payment_attempt_outcome.uncertain'))->toBeTrue()
            ->and(data_get($pending->payment_data, 'payment_attempt_outcome.status'))->toBe(PaymentStatus::Processing->value)
            ->and($tracker->creates)->toBe(1)
            ->and($pending->getCompensationLog())->toBe([]);

        Event::assertNotDispatched(CheckoutFailed::class);
        Event::assertNotDispatched(CheckoutStepFailed::class);

        // An ordinary duplicate caller observes the uncertain attempt instead
        // of driving a second gateway call.
        $duplicate = $service->processCheckout($pending->fresh());

        expect($duplicate->success)->toBeFalse()
            ->and($duplicate->metadata['processing'] ?? null)->toBeTrue()
            ->and($duplicate->metadata['reconciliation'] ?? null)->toBeTrue()
            ->and($tracker->creates)->toBe(1);

        // The public recovery reconciles the same frozen attempt identity.
        $recovered = $service->retryPayment(chk08KeepPrepStepsValid($session->fresh()));

        expect($recovered->requiresRedirect())->toBeTrue()
            ->and($recovered->redirectUrl)->toBe('https://gateway.example.test/pay/chk08')
            ->and($tracker->creates)->toBe(2)
            ->and($tracker->keys)->toBe([$expectedKey, $expectedKey])
            ->and($tracker->attempts)->toBe([1, 1]);

        $final = $session->fresh();

        expect($final->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($final->payment_attempts)->toBe(1)
            ->and($final->payment_id)->toBe('pay_chk08_recovered')
            ->and(data_get($final->payment_data, 'payment_attempt_outcome.status'))->toBe(PaymentStatus::Pending->value)
            ->and(data_get($final->payment_data, 'payment_attempt_outcome.uncertain'))->toBeFalse();

        Event::assertNotDispatched(CheckoutFailed::class);
    });

    it('reconciles an uncertain attempt even at the new-charge retry limit and only then refuses a new charge', function (): void {
        config()->set('checkout.payment.retry_limit', 1);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $tracker = chk08Tracker();

        $processor = chk08Processor(function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
            return $tracker->creates === 1
                ? PaymentResult::uncertain('Gateway timed out')
                : PaymentResult::failed('Declined by the gateway');
        }, $tracker);

        $service = chk08Service(chk08Resolver($processor), $tracker);

        $session = chk08Session('chk08-uncertain-retry-limit');
        $expectedKey = $session->getKey() . ':attempt:1';

        $service->processCheckout($session);

        expect($session->fresh()->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($session->fresh()->payment_attempts)->toBe(1);

        // Budget is exhausted for new charges, but reconciling the uncertain
        // attempt is not a new charge and must still run.
        $reconciled = $service->retryPayment(chk08KeepPrepStepsValid($session->fresh()));

        expect($reconciled->success)->toBeFalse()
            ->and($tracker->creates)->toBe(2)
            ->and($tracker->keys)->toBe([$expectedKey, $expectedKey])
            ->and($tracker->attempts)->toBe([1, 1]);

        $failed = $session->fresh();

        expect($failed->status)->toBeInstanceOf(PaymentFailedState::class)
            ->and($failed->payment_attempts)->toBe(1)
            ->and(data_get($failed->payment_data, 'payment_attempt_outcome.status'))->toBe(PaymentStatus::Failed->value)
            ->and(data_get($failed->payment_data, 'payment_attempt_outcome.definitive'))->toBeTrue();

        // Now the attempt is definitively failed on an exhausted budget: no
        // new generation may be released.
        expect(fn (): CheckoutResult => $service->retryPayment($failed->fresh()))
            ->toThrow(PaymentException::class);

        expect($tracker->creates)->toBe(2);
    });

    it('issues a new generation and idempotency key only after a definitive failure', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $tracker = chk08Tracker();

        $processor = chk08Processor(function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
            return match ($tracker->creates) {
                1 => PaymentResult::uncertain('Gateway timed out'),
                2 => PaymentResult::failed('Declined by the gateway'),
                default => PaymentResult::pending('pay_chk08_second', 'https://gateway.example.test/pay/chk08-second'),
            };
        }, $tracker);

        $service = chk08Service(chk08Resolver($processor), $tracker);

        $session = chk08Session('chk08-generation-release');
        $firstKey = $session->getKey() . ':attempt:1';
        $secondKey = $session->getKey() . ':attempt:2';

        $service->processCheckout($session);

        expect($session->fresh()->payment_attempts)->toBe(1);
        Event::assertNotDispatched(CheckoutFailed::class);

        $service->retryPayment(chk08KeepPrepStepsValid($session->fresh()));

        $failed = $session->fresh();

        expect($failed->status)->toBeInstanceOf(PaymentFailedState::class)
            ->and($failed->payment_attempts)->toBe(1)
            ->and($tracker->creates)->toBe(2)
            ->and($tracker->keys)->toBe([$firstKey, $firstKey]);

        Event::assertDispatchedTimes(CheckoutFailed::class, 1);

        $retried = $service->retryPayment(chk08KeepPrepStepsValid($failed->fresh()));

        expect($retried->requiresRedirect())->toBeTrue()
            ->and($tracker->creates)->toBe(3)
            ->and($tracker->keys)->toBe([$firstKey, $firstKey, $secondKey])
            ->and($tracker->attempts)->toBe([1, 1, 2]);

        $final = $session->fresh();

        expect($final->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($final->payment_attempts)->toBe(2)
            ->and(data_get($final->payment_data, 'payment_attempt.idempotency_key'))->toBe($secondKey)
            ->and(data_get($final->payment_data, 'payment_attempt_outcome.number'))->toBe(2);
    });

    it('releases a new generation after a verified failure callback for a pending redirect', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $tracker = chk08Tracker();

        $processor = chk08Processor(function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
            return PaymentResult::pending(
                $tracker->creates === 1 ? 'pay_chk08_pending_1' : 'pay_chk08_pending_2',
                $tracker->creates === 1
                    ? 'https://gateway.example.test/pay/chk08-pending-1'
                    : 'https://gateway.example.test/pay/chk08-pending-2',
            );
        }, $tracker);

        $service = chk08Service(chk08Resolver($processor), $tracker);

        $session = chk08Session('chk08-verified-callback-failure');
        $firstKey = $session->getKey() . ':attempt:1';
        $secondKey = $session->getKey() . ':attempt:2';

        $started = $service->processCheckout($session);

        expect($started->requiresRedirect())->toBeTrue()
            ->and($session->fresh()->payment_attempts)->toBe(1)
            ->and(data_get($session->fresh()->payment_data, 'payment_attempt_outcome.status'))->toBe(PaymentStatus::Pending->value);

        $callback = new HandleCheckoutPaymentCallback($service, new CheckoutCallbackStatePolicy);

        $callback->handle((string) $session->getKey(), 'failure', ['id' => 'pay_chk08_pending_1']);

        $failed = $session->fresh();

        expect($failed->status)->toBeInstanceOf(PaymentFailedState::class)
            ->and($failed->payment_attempts)->toBe(1)
            ->and(data_get($failed->payment_data, 'payment_attempt_outcome.status'))->toBe(PaymentStatus::Failed->value)
            ->and(data_get($failed->payment_data, 'payment_attempt_outcome.definitive'))->toBeTrue()
            ->and(data_get($failed->payment_data, 'payment_attempt_outcome.number'))->toBe(1)
            ->and($tracker->voided)->toBe(['pay_chk08_pending_1']);

        $retried = $service->retryPayment(chk08KeepPrepStepsValid($failed->fresh()));

        expect($retried->requiresRedirect())->toBeTrue()
            ->and($tracker->creates)->toBe(2)
            ->and($tracker->keys)->toBe([$firstKey, $secondKey])
            ->and($tracker->attempts)->toBe([1, 2]);

        $final = $session->fresh();

        expect($final->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($final->payment_attempts)->toBe(2)
            ->and(data_get($final->payment_data, 'payment_attempt_outcome.number'))->toBe(2)
            ->and(data_get($final->payment_data, 'payment_attempt_outcome.status'))->toBe(PaymentStatus::Pending->value);
    });

    it('does not fail or compensate a known processing outcome without a redirect', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $tracker = chk08Tracker();

        $processor = chk08Processor(fn (CheckoutSession $session, PaymentRequest $request): PaymentResult => PaymentResult::processing('pay_chk08_processing_1', 'chip'), $tracker);

        $service = chk08Service(chk08Resolver($processor), $tracker);

        $session = chk08Session('chk08-processing-without-redirect');

        $first = $service->processCheckout($session);

        expect($first->success)->toBeFalse()
            ->and($first->metadata['processing'] ?? null)->toBeTrue()
            ->and($first->metadata['reconciliation'] ?? false)->toBeFalse();

        $pending = $session->fresh();

        expect($pending->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($pending->payment_attempts)->toBe(1)
            ->and($pending->payment_id)->toBe('pay_chk08_processing_1')
            ->and(data_get($pending->payment_data, 'payment_attempt_outcome.status'))->toBe(PaymentStatus::Processing->value)
            ->and(data_get($pending->payment_data, 'payment_attempt_outcome.uncertain'))->toBeFalse()
            ->and($pending->getCompensationLog())->toBe([])
            ->and($tracker->creates)->toBe(1);

        Event::assertNotDispatched(CheckoutFailed::class);
        Event::assertNotDispatched(CheckoutStepFailed::class);

        // A duplicate caller observes; the in-flight charge is never re-posted
        // and never mistaken for a failure.
        $duplicate = $service->processCheckout($pending->fresh());

        expect($duplicate->success)->toBeFalse()
            ->and($duplicate->metadata['processing'] ?? null)->toBeTrue()
            ->and($tracker->creates)->toBe(1);

        $recovery = $service->retryPayment($pending->fresh());

        expect($recovery->success)->toBeFalse()
            ->and($recovery->metadata['processing'] ?? null)->toBeTrue()
            ->and($tracker->creates)->toBe(1);

        $final = $session->fresh();

        expect($final->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($final->payment_attempts)->toBe(1)
            ->and($final->getCompensationLog())->toBe([]);
    });

    it('reconciles repeat and competing recoveries on one generation with one frozen key', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $tracker = chk08Tracker();
        $holder = new class
        {
            public ?CheckoutService $service = null;

            public bool $competed = false;
        };

        $processor = chk08Processor(function (CheckoutSession $session, PaymentRequest $request) use ($tracker, $holder): PaymentResult {
            // While the first recovery is still in its gateway call, a second
            // worker attempts the same recovery. The row lock cannot span the
            // gateway I/O, so both must reconcile the same frozen attempt.
            if (! $holder->competed && $tracker->creates === 2) {
                $holder->competed = true;
                $tracker->competed = $holder->service?->retryPayment(CheckoutSession::find($session->getKey()));
            }

            return PaymentResult::uncertain('Gateway timed out');
        }, $tracker);

        $service = chk08Service(chk08Resolver($processor), $tracker, $holder);

        $session = chk08Session('chk08-competing-recoveries');
        $expectedKey = $session->getKey() . ':attempt:1';

        $service->processCheckout($session);

        expect($tracker->creates)->toBe(1)
            ->and($session->fresh()->payment_attempts)->toBe(1);

        $recovered = $service->retryPayment(chk08KeepPrepStepsValid($session->fresh()));

        expect($recovered->success)->toBeFalse()
            ->and($recovered->metadata['reconciliation'] ?? null)->toBeTrue()
            ->and($tracker->competed)->not->toBeNull()
            ->and($tracker->competed->metadata['reconciliation'] ?? null)->toBeTrue()
            ->and($tracker->creates)->toBe(3)
            ->and($tracker->keys)->toBe([$expectedKey, $expectedKey, $expectedKey])
            ->and($tracker->attempts)->toBe([1, 1, 1]);

        $stillPending = $session->fresh();

        expect($stillPending->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($stillPending->payment_attempts)->toBe(1)
            ->and($stillPending->getCompensationLog())->toBe([]);

        // A later repeat recovery still reconciles the same generation.
        $service->retryPayment($stillPending->fresh());

        expect($tracker->creates)->toBe(4)
            ->and($tracker->keys)->toBe([$expectedKey, $expectedKey, $expectedKey, $expectedKey])
            ->and($session->fresh()->payment_attempts)->toBe(1);

        Event::assertNotDispatched(CheckoutFailed::class);
        Event::assertNotDispatched(CheckoutStepFailed::class);
    });
});

describe('built-in processor exception paths', function (): void {
    it('recovers a real CHIP processor timeout with the frozen attempt identity', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        config()->set('chip.collect.brand_id', 'brand-chk08');
        config()->set('chip.collect.api_key', 'api-key-chk08');
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $purchase = PurchaseData::from([
            'id' => 'purchase_chk08_1',
            'type' => 'purchase',
            'status' => 'created',
            'created_on' => time(),
            'updated_on' => time(),
            'client' => ['email' => 'checkout@example.com'],
            'purchase' => [
                'total' => 1000,
                'currency' => 'MYR',
                'products' => [],
            ],
            'checkout_url' => 'https://gate.chip-in.asia/checkout/purchase_chk08_1',
        ]);

        $calls = 0;
        $keys = [];

        Chip::shouldReceive('createPurchase')
            ->twice()
            ->andReturnUsing(function (array $payload, ?string $key) use (&$calls, &$keys, $purchase): PurchaseData {
                $calls++;
                $keys[] = $key;

                if ($calls === 1) {
                    throw new ConnectionException('cURL error 28: Operation timed out');
                }

                return $purchase;
            });

        $statusMapper = new ChipPaymentStatusMapper;
        $processor = new ChipProcessor(
            new ChipPurchasePayloadBuilder,
            $statusMapper,
            new ChipRefundGateway($statusMapper),
        );

        $tracker = chk08Tracker();
        $service = chk08Service(chk08Resolver($processor), $tracker);

        $session = chk08Session('chk08-real-chip-timeout');
        $expectedKey = $session->getKey() . ':attempt:1';

        $first = $service->processCheckout($session);

        expect($first->success)->toBeFalse()
            ->and($first->metadata['reconciliation'] ?? null)->toBeTrue();

        $pending = $session->fresh();

        expect($pending->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($pending->payment_attempts)->toBe(1)
            ->and(data_get($pending->payment_data, 'payment_attempt_outcome.uncertain'))->toBeTrue()
            ->and($pending->getCompensationLog())->toBe([])
            ->and($calls)->toBe(1);

        Event::assertNotDispatched(CheckoutFailed::class);
        Event::assertNotDispatched(CheckoutStepFailed::class);

        $recovered = $service->retryPayment(chk08KeepPrepStepsValid($session->fresh()));

        expect($recovered->requiresRedirect())->toBeTrue()
            ->and($recovered->redirectUrl)->toBe('https://gate.chip-in.asia/checkout/purchase_chk08_1')
            ->and($calls)->toBe(2)
            ->and($keys)->toBe([$expectedKey, $expectedKey]);

        $final = $session->fresh();

        expect($final->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($final->payment_attempts)->toBe(1)
            ->and($final->payment_id)->toBe('purchase_chk08_1')
            ->and(data_get($final->payment_data, 'payment_attempt.idempotency_key'))->toBe($expectedKey);
    });

    it('recovers a real Cashier processor exception through the frozen provider', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $tracker = chk08Tracker();

        $gateway = new Chk08CapturingChipGateway;
        $gateway->failOnFirstCharge = true;
        $gateway->queueResult(new Chk08FakePayment(
            'pay_chk08_cashier',
            'https://gateway.example.test/pay/chk08-cashier',
            'pending',
            1000,
        ));

        $manager = new Chk08GatewayManager(app());
        $manager->gateways['chip'] = $gateway;
        app()->instance(GatewayManager::class, $manager);

        $resolver = new PaymentGatewayResolver(defaultGateway: 'cashier');
        $resolver->register('cashier', new CashierProcessor);

        $service = chk08Service($resolver, $tracker);

        $session = chk08BillableSession('chk08-real-cashier-exception');
        $expectedKey = $session->getKey() . ':attempt:1';

        $first = $service->processCheckout($session);

        expect($first->success)->toBeFalse()
            ->and($first->metadata['reconciliation'] ?? null)->toBeTrue();

        $pending = $session->fresh();

        expect($pending->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($pending->payment_attempts)->toBe(1)
            ->and(data_get($pending->payment_data, 'payment_attempt_outcome.uncertain'))->toBeTrue()
            ->and(data_get($pending->payment_data, 'payment_attempt.provider'))->toBe('chip')
            ->and($pending->getCompensationLog())->toBe([]);

        Event::assertNotDispatched(CheckoutFailed::class);
        Event::assertNotDispatched(CheckoutStepFailed::class);

        $recovered = $service->retryPayment(chk08KeepPrepStepsValid(chk08Reload($session)));

        expect($recovered->requiresRedirect())->toBeTrue()
            ->and($recovered->redirectUrl)->toBe('https://gateway.example.test/pay/chk08-cashier');

        expect($gateway->charges)->toHaveCount(2)
            ->and($gateway->charges[0]['options']['idempotency_key'])->toBe($expectedKey)
            ->and($gateway->charges[1]['options']['idempotency_key'])->toBe($expectedKey)
            ->and($gateway->charges[0]['options']['metadata']['checkout_attempt'])->toBe(1)
            ->and($gateway->charges[1]['options']['metadata']['checkout_attempt'])->toBe(1)
            ->and($manager->requested)->toBe(['chip', 'chip']);

        $final = $session->fresh();

        expect($final->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($final->payment_attempts)->toBe(1)
            ->and($final->payment_id)->toBe('pay_chk08_cashier');
    });

    it('recovers a real CashierChip processor exception with the frozen key', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        config()->set('chip.collect.brand_id', 'brand-chk08');
        config()->set('chip.collect.api_key', 'api-key-chk08');
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $purchase = PurchaseData::from([
            'id' => 'purchase_chk08_cashier_chip',
            'type' => 'purchase',
            'status' => 'created',
            'created_on' => time(),
            'updated_on' => time(),
            'client' => ['email' => 'checkout@example.com'],
            'purchase' => [
                'total' => 1000,
                'currency' => 'MYR',
                'products' => [],
            ],
            'checkout_url' => 'https://gate.chip-in.asia/checkout/purchase_chk08_cashier_chip',
        ]);

        $calls = 0;
        $keys = [];

        Chip::shouldReceive('createPurchase')
            ->twice()
            ->andReturnUsing(function (array $payload, ?string $key) use (&$calls, &$keys, $purchase): PurchaseData {
                $calls++;
                $keys[] = $key;

                if ($calls === 1) {
                    throw new ConnectionException('cURL error 28: Operation timed out');
                }

                return $purchase;
            });

        $statusMapper = new ChipPaymentStatusMapper;
        $processor = new CashierChipProcessor(
            new ChipPurchasePayloadBuilder,
            $statusMapper,
            new ChipRefundGateway($statusMapper),
        );

        $tracker = chk08Tracker();
        $service = chk08Service(chk08Resolver($processor, 'cashier-chip'), $tracker);

        $session = chk08Session('chk08-real-cashier-chip-timeout', 'cashier-chip');
        $expectedKey = $session->getKey() . ':attempt:1';

        $first = $service->processCheckout($session);

        expect($first->success)->toBeFalse()
            ->and($first->metadata['reconciliation'] ?? null)->toBeTrue();

        $pending = $session->fresh();

        expect($pending->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($pending->payment_attempts)->toBe(1)
            ->and(data_get($pending->payment_data, 'payment_attempt_outcome.uncertain'))->toBeTrue()
            ->and($pending->getCompensationLog())->toBe([])
            ->and($calls)->toBe(1);

        Event::assertNotDispatched(CheckoutFailed::class);
        Event::assertNotDispatched(CheckoutStepFailed::class);

        $recovered = $service->retryPayment(chk08KeepPrepStepsValid($session->fresh()));

        expect($recovered->requiresRedirect())->toBeTrue()
            ->and($recovered->redirectUrl)->toBe('https://gate.chip-in.asia/checkout/purchase_chk08_cashier_chip')
            ->and($calls)->toBe(2)
            ->and($keys)->toBe([$expectedKey, $expectedKey]);

        $final = $session->fresh();

        expect($final->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($final->payment_attempts)->toBe(1)
            ->and($final->payment_id)->toBe('purchase_chk08_cashier_chip')
            ->and(data_get($final->payment_data, 'payment_attempt.idempotency_key'))->toBe($expectedKey);
    });
});
