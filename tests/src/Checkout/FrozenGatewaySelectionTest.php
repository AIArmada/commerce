<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\Checkout\Actions\CheckoutFinalizer;
use AIArmada\Checkout\Actions\ProcessCheckoutPaymentNotification;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\PaymentCompensationInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Contracts\ProviderAwarePaymentProcessorInterface;
use AIArmada\Checkout\Data\CheckoutResult;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Events\CheckoutCancelled;
use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Checkout\Events\CheckoutFailed;
use AIArmada\Checkout\Events\CheckoutPaymentCompleted;
use AIArmada\Checkout\Events\CheckoutStepFailed;
use AIArmada\Checkout\Http\Controllers\PaymentCallbackController;
use AIArmada\Checkout\Integrations\Payment\CashierProcessor;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use AIArmada\Checkout\Services\StepExecutor;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Cancelled;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Checkout\Support\CheckoutPaymentReference;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\mock;

/*
|--------------------------------------------------------------------------
| CHK-03: the actual gateway/provider is frozen into the attempt identity
|--------------------------------------------------------------------------
|
| The current gateway selection and provider hint may shape a brand-new
| attempt generation, but a recovered (crashed or uncertain) attempt must
| be driven entirely through its frozen identity: the original processor,
| request gateway, concrete provider, payment method, and idempotency key.
| Availability is judged for the attempt that is actually recovered, not
| for a newly selected gateway, and status, compensation, and orphan paths
| resolve the same frozen identity.
|
*/

beforeEach(function (): void {
    Model::clearBootedModels();
});

function chk03Tracker(): object
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

        /** @var list<string|null> */
        public array $paymentMethods = [];

        /** @var list<string|null> */
        public array $resolveProviders = [];

        /** @var list<string> */
        public array $statusChecks = [];

        /** @var list<string> */
        public array $voided = [];

        /** @var list<string> */
        public array $refunded = [];

        public int $callbacks = 0;
    };
}

function chk03Step(string $identifier, array $dependencies = []): CheckoutStepInterface
{
    return new class($identifier, $dependencies) implements CheckoutStepInterface
    {
        public function __construct(
            private readonly string $identifier,
            private readonly array $dependencies,
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

function chk03Session(string $cartId, string $gateway = 'old', int $total = 1000, array $overrides = []): CheckoutSession
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

function chk03Processor(
    string $identifier,
    object $tracker,
    ?Closure $onCreate = null,
    bool $available = true,
    ?PaymentResult $statusResult = null,
): PaymentProcessorInterface {
    return new class($identifier, $tracker, $onCreate, $available, $statusResult) implements PaymentCompensationInterface, PaymentProcessorInterface
    {
        public function __construct(
            private readonly string $identifier,
            private readonly object $tracker,
            private readonly ?Closure $onCreate,
            private readonly bool $available,
            private readonly ?PaymentResult $statusResult,
        ) {}

        public function getIdentifier(): string
        {
            return $this->identifier;
        }

        public function getName(): string
        {
            return "checkout-chk03-{$this->identifier}";
        }

        public function isAvailable(CheckoutSession $session): bool
        {
            return $this->available;
        }

        public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
        {
            $this->tracker->creates++;
            $this->tracker->keys[] = $request->metadata['checkout_idempotency_key'] ?? null;
            $this->tracker->attempts[] = $request->metadata['checkout_attempt'] ?? null;
            $this->tracker->gateways[] = $request->gateway;
            $this->tracker->providers[] = $request->provider;
            $this->tracker->paymentMethods[] = $request->paymentMethod;

            return $this->onCreate !== null
                ? ($this->onCreate)($session, $request)
                : PaymentResult::failed('unused');
        }

        public function handleCallback(array $payload): PaymentResult
        {
            $this->tracker->callbacks++;

            return PaymentResult::failed('unused');
        }

        public function getRedirectUrl(CheckoutSession $session): ?string
        {
            return null;
        }

        public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
        {
            $this->tracker->refunded[] = $paymentId;

            return new PaymentResult(status: PaymentStatus::Refunded, paymentId: $paymentId);
        }

        public function checkStatus(string $paymentId): PaymentResult
        {
            $this->tracker->statusChecks[] = $paymentId;

            return $this->statusResult ?? PaymentResult::processing($paymentId);
        }

        public function voidPayment(string $paymentId, ?string $reason = null): PaymentResult
        {
            $this->tracker->voided[] = $paymentId;

            return new PaymentResult(status: PaymentStatus::Cancelled, paymentId: $paymentId);
        }
    };
}

function chk03ProviderAwareProcessor(
    string $identifier,
    object $tracker,
    Closure $onCreate,
    ?string $defaultProvider = null,
): PaymentProcessorInterface {
    return new class($identifier, $tracker, $onCreate, $defaultProvider) implements ProviderAwarePaymentProcessorInterface
    {
        public function __construct(
            private readonly string $identifier,
            private readonly object $tracker,
            private readonly Closure $onCreate,
            private readonly ?string $defaultProvider,
        ) {}

        public function getIdentifier(): string
        {
            return $this->identifier;
        }

        public function getName(): string
        {
            return "checkout-chk03-{$this->identifier}";
        }

        public function isAvailable(CheckoutSession $session): bool
        {
            return true;
        }

        public function resolveProvider(?string $selectedProvider = null): ?string
        {
            $this->tracker->resolveProviders[] = $selectedProvider;

            $selected = is_string($selectedProvider) ? mb_trim($selectedProvider) : '';

            return $selected !== '' ? $selected : $this->defaultProvider;
        }

        public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
        {
            $this->tracker->creates++;
            $this->tracker->keys[] = $request->metadata['checkout_idempotency_key'] ?? null;
            $this->tracker->attempts[] = $request->metadata['checkout_attempt'] ?? null;
            $this->tracker->gateways[] = $request->gateway;
            $this->tracker->providers[] = $request->provider;
            $this->tracker->paymentMethods[] = $request->paymentMethod;

            return ($this->onCreate)($session, $request);
        }

        public function handleCallback(array $payload): PaymentResult
        {
            $this->tracker->callbacks++;

            return PaymentResult::failed('unused');
        }

        public function getRedirectUrl(CheckoutSession $session): ?string
        {
            return null;
        }

        public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
        {
            return $this->refundForProvider($this->identifier, $paymentId, $amount, $reason);
        }

        public function refundForProvider(
            string $provider,
            string $paymentId,
            int $amount,
            ?string $reason = null,
        ): PaymentResult {
            $this->tracker->refunded[] = $paymentId;

            return new PaymentResult(status: PaymentStatus::Refunded, paymentId: $paymentId);
        }

        public function checkStatus(string $paymentId): PaymentResult
        {
            return $this->checkStatusForProvider($this->identifier, $paymentId);
        }

        public function checkStatusForProvider(string $provider, string $paymentId): PaymentResult
        {
            $this->tracker->statusChecks[] = $paymentId;

            return PaymentResult::processing($paymentId);
        }

        public function voidPayment(string $paymentId, ?string $reason = null): PaymentResult
        {
            return $this->voidPaymentForProvider($this->identifier, $paymentId, $reason);
        }

        public function voidPaymentForProvider(
            string $provider,
            string $paymentId,
            ?string $reason = null,
        ): PaymentResult {
            $this->tracker->voided[] = $paymentId;

            return new PaymentResult(status: PaymentStatus::Cancelled, paymentId: $paymentId);
        }
    };
}

function chk03Service(PaymentGatewayResolver $resolver): CheckoutService
{
    $registry = new CheckoutStepRegistry;
    $registry->register('calculate_shipping', chk03Step('calculate_shipping'));
    $registry->register('process_payment', new ProcessPaymentStep($resolver));
    $registry->register('create_order', chk03Step('create_order', ['process_payment']));
    $registry->setOrder(['calculate_shipping', 'process_payment', 'create_order']);

    return new CheckoutService(
        stepRegistry: $registry,
        events: app(Dispatcher::class),
        stepExecutor: new StepExecutor($registry, app(Dispatcher::class)),
        finalizer: new CheckoutFinalizer(app(Dispatcher::class)),
        paymentResolver: $resolver,
    );
}

/**
 * Compensation rolls back every completed step, including the pre-payment
 * steps. A retry harness keeps those valid so the retry reaches the payment
 * step, matching the existing retry-limit regression convention.
 */
function chk03KeepPrepStepsValid(CheckoutSession $session): CheckoutSession
{
    $session->setStepState('calculate_shipping', StepStatus::Completed);

    return $session;
}

function chk03MutateSelection(CheckoutSession $session, string $gateway, ?string $provider, ?string $paymentMethod = null): CheckoutSession
{
    $paymentData = $session->payment_data ?? [];
    $paymentData['provider'] = $provider;

    if ($paymentMethod !== null) {
        $paymentData['payment_method'] = $paymentMethod;
    }

    $session->persistState([
        'selected_payment_gateway' => $gateway,
        'payment_data' => $paymentData,
    ]);

    return $session;
}

final class Chk03Session extends CheckoutSession
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

function chk03BillableSession(string $cartId, string $gateway = 'cashier'): Chk03Session
{
    $session = Chk03Session::forceCreate([
        'cart_id' => $cartId,
        'status' => Pending::class,
        'selected_payment_gateway' => $gateway,
        'grand_total' => 1000,
        'currency' => 'MYR',
        'step_states' => [
            'calculate_shipping' => 'pending',
            'process_payment' => 'pending',
            'create_order' => 'pending',
        ],
    ]);

    return $session->pinRelation('billable', mock(BillableContract::class));
}

function chk03Reload(Chk03Session $session): Chk03Session
{
    $billable = $session->billable;

    return $session->fresh()->pinRelation('billable', $billable);
}

final class Chk03FakePayment implements PaymentContract
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
 * Real CHIP gateway whose transport is captured, named per gateway, and can
 * fail once to simulate an uncertain creation response.
 */
final class Chk03CapturingGateway extends ChipGateway
{
    /** @var list<array{billable: BillableContract, amount: int, payment_method: ?string, options: array<string, mixed>}> */
    public array $charges = [];

    public bool $failOnFirstCharge = false;

    /** @var list<PaymentContract> */
    private array $results = [];

    public function __construct(private readonly string $gatewayName = 'chip') {}

    public function name(): string
    {
        return $this->gatewayName;
    }

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

        return array_shift($this->results) ?? new Chk03FakePayment('pay_unexpected', null, 'failed');
    }
}

/**
 * Gateway manager exposing one capturing gateway per cashier driver and
 * recording every driver resolution so the default can be mutated between
 * the claim and the recovery.
 */
final class Chk03GatewayManager extends GatewayManager
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

describe('frozen gateway and provider selection', function (): void {
    it('drives adoption through the frozen gateway and request identity after the selection changes', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $oldTracker = chk03Tracker();
        $newTracker = chk03Tracker();

        $oldProcessor = chk03Processor('old', $oldTracker, function () use ($oldTracker): PaymentResult {
            return $oldTracker->creates === 1
                ? PaymentResult::uncertain('Gateway timed out')
                : PaymentResult::pending('pay_old_recovered', 'https://gateway.example.test/old/recovered');
        });
        $newProcessor = chk03Processor('new', $newTracker, fn (): PaymentResult => PaymentResult::pending('pay_new', 'https://gateway.example.test/new'));

        $resolver = new PaymentGatewayResolver(defaultGateway: 'old');
        $resolver->register('old', $oldProcessor);
        $resolver->register('new', $newProcessor);

        $service = chk03Service($resolver);

        $session = chk03Session('chk03-adoption-frozen-gateway', 'old', 1000, [
            'payment_data' => [
                'provider' => 'provider-old',
                'payment_method' => 'card_old',
            ],
        ]);

        $expectedKey = $session->getKey() . ':attempt:1';

        $first = $service->processCheckout($session);

        expect($first->metadata['reconciliation'] ?? null)->toBeTrue()
            ->and($oldTracker->creates)->toBe(1)
            ->and($session->fresh()->status)->toBeInstanceOf(PaymentProcessing::class);

        chk03MutateSelection($session->fresh(), 'new', 'provider-new', 'card_new');

        $recovered = $service->retryPayment(chk03KeepPrepStepsValid($session->fresh()));

        expect($recovered->requiresRedirect())->toBeTrue()
            ->and($recovered->redirectUrl)->toBe('https://gateway.example.test/old/recovered')
            ->and($oldTracker->creates)->toBe(2)
            ->and($newTracker->creates)->toBe(0)
            ->and($oldTracker->gateways)->toBe(['old', 'old'])
            ->and($newTracker->gateways)->toBe([])
            ->and($oldTracker->keys)->toBe([$expectedKey, $expectedKey])
            ->and($oldTracker->attempts)->toBe([1, 1])
            ->and($oldTracker->providers)->toBe(['provider-old', 'provider-old'])
            ->and($oldTracker->paymentMethods)->toBe(['card_old', 'card_old']);

        $final = $session->fresh();

        expect($final->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($final->payment_attempts)->toBe(1)
            ->and($final->payment_id)->toBe('pay_old_recovered')
            ->and(data_get($final->payment_data, 'payment_attempt.gateway'))->toBe('old')
            ->and(data_get($final->payment_data, 'payment_attempt.provider'))->toBe('provider-old')
            ->and(data_get($final->payment_data, 'payment_attempt.idempotency_key'))->toBe($expectedKey);

        Event::assertNotDispatched(CheckoutFailed::class);
        Event::assertNotDispatched(CheckoutStepFailed::class);
    });

    it('keeps recovery on the frozen gateway when the newly selected processor is unavailable', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $oldTracker = chk03Tracker();
        $newTracker = chk03Tracker();

        $oldProcessor = chk03Processor('old', $oldTracker, function () use ($oldTracker): PaymentResult {
            return $oldTracker->creates === 1
                ? PaymentResult::uncertain('Gateway timed out')
                : PaymentResult::pending('pay_old_unavailable_selection', 'https://gateway.example.test/old/unavailable');
        });
        $newProcessor = chk03Processor(
            'new',
            $newTracker,
            fn (): PaymentResult => PaymentResult::pending('pay_new_should_not_run', 'https://gateway.example.test/new'),
            available: false,
        );

        $resolver = new PaymentGatewayResolver(defaultGateway: 'old');
        $resolver->register('old', $oldProcessor);
        $resolver->register('new', $newProcessor);

        $service = chk03Service($resolver);

        $session = chk03Session('chk03-unavailable-selection', 'old', 1000, [
            'payment_data' => ['provider' => 'provider-old'],
        ]);

        $service->processCheckout($session);

        expect($session->fresh()->status)->toBeInstanceOf(PaymentProcessing::class);

        chk03MutateSelection($session->fresh(), 'new', 'provider-new');

        $recovered = $service->retryPayment(chk03KeepPrepStepsValid($session->fresh()));

        expect($recovered->requiresRedirect())->toBeTrue()
            ->and($recovered->redirectUrl)->toBe('https://gateway.example.test/old/unavailable')
            ->and($oldTracker->creates)->toBe(2)
            ->and($newTracker->creates)->toBe(0);

        $final = $session->fresh();

        expect($final->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($final->payment_attempts)->toBe(1)
            ->and($final->payment_id)->toBe('pay_old_unavailable_selection');
    });

    it('keeps recovery on the frozen gateway when the newly selected gateway is not registered', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $oldTracker = chk03Tracker();

        $oldProcessor = chk03Processor('old', $oldTracker, function () use ($oldTracker): PaymentResult {
            return $oldTracker->creates === 1
                ? PaymentResult::uncertain('Gateway timed out')
                : PaymentResult::pending('pay_old_unregistered_selection', 'https://gateway.example.test/old/unregistered');
        });

        $resolver = new PaymentGatewayResolver(defaultGateway: 'old');
        $resolver->register('old', $oldProcessor);

        $service = chk03Service($resolver);

        $session = chk03Session('chk03-unregistered-selection', 'old', 1000, [
            'payment_data' => ['provider' => 'provider-old'],
        ]);

        $service->processCheckout($session);

        expect($session->fresh()->status)->toBeInstanceOf(PaymentProcessing::class);

        chk03MutateSelection($session->fresh(), 'ghost', 'provider-new');

        $recovered = $service->retryPayment(chk03KeepPrepStepsValid($session->fresh()));

        expect($recovered->requiresRedirect())->toBeTrue()
            ->and($recovered->redirectUrl)->toBe('https://gateway.example.test/old/unregistered')
            ->and($oldTracker->creates)->toBe(2);

        $final = $session->fresh();

        expect($final->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($final->payment_attempts)->toBe(1)
            ->and($final->payment_id)->toBe('pay_old_unregistered_selection');
    });

    it('freezes the concrete Cashier default provider and replays it after the default changes', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        config()->set('cashier.default', 'chip');
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $chip = new Chk03CapturingGateway('chip');
        $chip->failOnFirstCharge = true;
        $chip->queueResult(new Chk03FakePayment('pay_cashier_recovered', 'https://gateway.example.test/cashier', 'pending', 1000));

        $stripe = new Chk03CapturingGateway('stripe');

        $manager = new Chk03GatewayManager(app());
        $manager->gateways['chip'] = $chip;
        $manager->gateways['stripe'] = $stripe;
        app()->instance(GatewayManager::class, $manager);

        $resolver = new PaymentGatewayResolver(defaultGateway: 'cashier');
        $resolver->register('cashier', new CashierProcessor);

        $service = chk03Service($resolver);

        $session = chk03BillableSession('chk03-cashier-default');

        $first = $service->processCheckout($session);

        expect($first->metadata['reconciliation'] ?? null)->toBeTrue()
            ->and($chip->charges)->toHaveCount(1);

        $pending = $session->fresh();

        expect(data_get($pending->payment_data, 'payment_attempt.provider'))->toBe('chip');

        // The cashier default flips between the claim and the recovery. The
        // frozen attempt must replay through the original concrete provider.
        config()->set('cashier.default', 'stripe');

        $recovered = $service->retryPayment(chk03KeepPrepStepsValid(chk03Reload($session)));

        expect($recovered->requiresRedirect())->toBeTrue()
            ->and($recovered->redirectUrl)->toBe('https://gateway.example.test/cashier')
            ->and($chip->charges)->toHaveCount(2)
            ->and($stripe->charges)->toHaveCount(0)
            ->and($manager->requested)->toBe(['chip', 'chip']);

        $final = $session->fresh();

        expect($final->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($final->payment_attempts)->toBe(1)
            ->and($final->payment_id)->toBe('pay_cashier_recovered')
            ->and(data_get($final->payment_data, 'payment_attempt.provider'))->toBe('chip');
    });

    it('applies the changed gateway and provider to a genuinely new generation after a definitive failure', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $oldTracker = chk03Tracker();
        $newTracker = chk03Tracker();

        $oldProcessor = chk03Processor('old', $oldTracker, fn (): PaymentResult => PaymentResult::failed('Declined by the old gateway'));
        $newProcessor = chk03ProviderAwareProcessor(
            'new',
            $newTracker,
            fn (): PaymentResult => PaymentResult::pending('pay_new_generation', 'https://gateway.example.test/new/generation'),
            'provider-new-default',
        );

        $resolver = new PaymentGatewayResolver(defaultGateway: 'old');
        $resolver->register('old', $oldProcessor);
        $resolver->register('new', $newProcessor);

        $service = chk03Service($resolver);

        $session = chk03Session('chk03-new-generation-selection', 'old', 1000, [
            'payment_data' => ['provider' => 'provider-old'],
        ]);

        $service->processCheckout($session);

        expect($session->fresh()->payment_attempts)->toBe(1);

        // A definitive failure releases a replacement generation, and the
        // changed selection plus cleared provider now apply to it.
        chk03MutateSelection($session->fresh(), 'new', null);

        $retried = $service->retryPayment(chk03KeepPrepStepsValid($session->fresh()));

        expect($retried->requiresRedirect())->toBeTrue()
            ->and($oldTracker->creates)->toBe(1)
            ->and($newTracker->creates)->toBe(1)
            ->and($newTracker->gateways)->toBe(['new'])
            ->and($newTracker->providers)->toBe(['provider-new-default'])
            ->and($newTracker->keys)->toBe([$session->getKey() . ':attempt:2']);

        $final = $session->fresh();

        expect($final->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($final->payment_attempts)->toBe(2)
            ->and(data_get($final->payment_data, 'payment_attempt.gateway'))->toBe('new')
            ->and(data_get($final->payment_data, 'payment_attempt.provider'))->toBe('provider-new-default');
    });

    it('compensates a superseded redirect through the frozen gateway after the selection changes', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutCancelled::class]);

        $oldTracker = chk03Tracker();
        $newTracker = chk03Tracker();

        $oldProcessor = chk03Processor('old', $oldTracker, fn (): PaymentResult => PaymentResult::pending('pay_old_redirect', 'https://gateway.example.test/old/redirect'));
        $newProcessor = chk03Processor('new', $newTracker, fn (): PaymentResult => PaymentResult::pending('pay_new_redirect', 'https://gateway.example.test/new/redirect'));

        $resolver = new PaymentGatewayResolver(defaultGateway: 'old');
        $resolver->register('old', $oldProcessor);
        $resolver->register('new', $newProcessor);

        $service = chk03Service($resolver);

        $session = chk03Session('chk03-compensation-frozen-gateway', 'old', 1000, [
            'payment_data' => ['provider' => 'provider-old'],
        ]);

        $started = $service->processCheckout($session);

        expect($started->requiresRedirect())->toBeTrue()
            ->and($session->fresh()->payment_id)->toBe('pay_old_redirect');

        chk03MutateSelection($session->fresh(), 'new', 'provider-new');

        $cancelled = $service->cancelCheckout($session->fresh());

        expect($cancelled->status)->toBeInstanceOf(Cancelled::class)
            ->and($oldTracker->voided)->toBe(['pay_old_redirect'])
            ->and($newTracker->voided)->toBe([])
            ->and($oldTracker->refunded)->toBe([])
            ->and($newTracker->refunded)->toBe([]);
    });

    it('polls the current redirect status through the frozen gateway after the selection changes', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class, CheckoutFailed::class]);

        $oldTracker = chk03Tracker();
        $newTracker = chk03Tracker();

        $oldProcessor = chk03Processor('old', $oldTracker, fn (): PaymentResult => PaymentResult::pending('pay_old_poll', 'https://gateway.example.test/old/poll'));
        $newProcessor = chk03Processor('new', $newTracker, fn (): PaymentResult => PaymentResult::pending('pay_new_poll', 'https://gateway.example.test/new/poll'));

        $resolver = new PaymentGatewayResolver(defaultGateway: 'old');
        $resolver->register('old', $oldProcessor);
        $resolver->register('new', $newProcessor);

        $service = chk03Service($resolver);

        $session = chk03Session('chk03-status-frozen-gateway', 'old', 1000, [
            'payment_data' => ['provider' => 'provider-old'],
        ]);

        $service->processCheckout($session);

        expect($session->fresh()->payment_id)->toBe('pay_old_poll');

        chk03MutateSelection($session->fresh(), 'new', 'provider-new');

        $result = $service->handlePaymentCallback($session->fresh(), 'success', []);

        expect($result->success)->toBeFalse()
            ->and($oldTracker->statusChecks)->toBe(['pay_old_poll'])
            ->and($newTracker->statusChecks)->toBe([])
            ->and($newTracker->callbacks)->toBe(0)
            ->and($newTracker->creates)->toBe(0);
    });
});

/*
|--------------------------------------------------------------------------
| CHK-09: inbound callbacks route through the frozen attempt gateway
|--------------------------------------------------------------------------
|
| The mutable selection may change after an attempt is claimed. Return URLs
| and provider notifications that arrive for the frozen gateway must still
| reach the attempt's processor, and URLs/notifications for the newly
| selected gateway must not. The route controller and the notification
| action share one authoritative resolver and revalidate it under the row
| lock inside the callback handler.
|
*/

function chk09FrozenAttemptSession(string $cartId, string $frozenGateway, string $selectedGateway): CheckoutSession
{
    $session = CheckoutSession::forceCreate([
        'cart_id' => $cartId,
        'status' => Pending::class,
        'selected_payment_gateway' => $selectedGateway,
        'grand_total' => 1000,
        'currency' => 'MYR',
        'payment_attempts' => 1,
        'payment_data' => [
            'payment_attempt' => [
                'number' => 1,
                'gateway' => $frozenGateway,
                'provider' => null,
                'amount' => 1000,
                'currency' => 'MYR',
            ],
            'callback_token' => 'chk09-callback-token',
            'callback_token_created_at' => now()->toIso8601String(),
        ],
    ]);

    $session->transitionStatus(Processing::class);
    $session = $session->fresh();
    $session->transitionStatus(PaymentProcessing::class);

    return $session->fresh();
}

describe('inbound callback routing uses the frozen attempt gateway', function (): void {
    it('accepts the frozen gateway callback route and rejects the newly selected route', function (string $callbackType): void {
        foreach (['success', 'failure', 'cancel'] as $type) {
            config()->set("checkout.routes.callbacks.{$type}", [
                'old' => "payment/old/{$type}",
                'new' => "payment/new/{$type}",
            ]);
        }
        config()->set('checkout.redirects.failure', '/checkout/failed');

        $session = chk09FrozenAttemptSession("chk09-route-frozen-{$callbackType}", 'old', 'new');

        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')
            ->once()
            ->withArgs(fn (CheckoutSession $candidate, string $type): bool => $candidate->id === $session->id && $type === $callbackType)
            ->andReturn(CheckoutResult::failed($session, 'Frozen gateway verification stub'));
        app()->instance(CheckoutServiceInterface::class, $checkoutService);

        $controller = app(PaymentCallbackController::class);

        $frozenRoute = new Route(['GET'], "payment/old/{$callbackType}", [
            'as' => "checkout.payment.old.{$callbackType}",
            'uses' => static function (): void {},
        ]);
        $frozenRequest = Request::create("/checkout/payment/old/{$callbackType}", 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'chk09-callback-token',
        ]);
        $frozenRequest->setRouteResolver(static fn (): Route => $frozenRoute);

        $frozenResponse = $controller->{$callbackType}($frozenRequest);

        if ($callbackType === 'success') {
            expect($frozenResponse->getSession()->get('error'))->toBe('Frozen gateway verification stub');
        } elseif ($callbackType === 'failure') {
            expect($frozenResponse->getSession()->get('error'))->toBe('Payment failed');
        } else {
            expect($frozenResponse->getSession()->get('error'))->toBeNull();
        }

        $selectedRoute = new Route(['GET'], "payment/new/{$callbackType}", [
            'as' => "checkout.payment.new.{$callbackType}",
            'uses' => static function (): void {},
        ]);
        $selectedRequest = Request::create("/checkout/payment/new/{$callbackType}", 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'chk09-callback-token',
        ]);
        $selectedRequest->setRouteResolver(static fn (): Route => $selectedRoute);

        $selectedResponse = $controller->{$callbackType}($selectedRequest);

        expect($selectedResponse->getSession()->get('error'))->toBe('Checkout session not found');
    })->with(['success', 'failure', 'cancel']);

    it('processes notifications for the frozen gateway and ignores the newly selected gateway', function (): void {
        $session = chk09FrozenAttemptSession('chk09-notification-frozen', 'chip', 'cashier');

        $checkoutService = mock(CheckoutServiceInterface::class);
        $checkoutService->shouldReceive('handlePaymentCallback')
            ->once()
            ->withArgs(fn (CheckoutSession $candidate, string $type): bool => $candidate->id === $session->id && $type === 'success')
            ->andReturn(CheckoutResult::success($session));
        app()->instance(CheckoutServiceInterface::class, $checkoutService);
        app()->forgetInstance(ProcessCheckoutPaymentNotification::class);

        $action = app(ProcessCheckoutPaymentNotification::class);

        $action->handle(
            payload: ['reference' => CheckoutPaymentReference::forSession($session)],
            expectedGateways: ['chip', 'cashier-chip'],
            callbackType: 'success',
        );

        $action->handle(
            payload: ['metadata' => ['checkout_session_id' => CheckoutPaymentReference::forSession($session)]],
            expectedGateways: ['cashier'],
            callbackType: 'success',
        );
    });
});
