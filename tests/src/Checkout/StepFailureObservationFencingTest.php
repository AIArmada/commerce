<?php

declare(strict_types=1);

use AIArmada\Checkout\Actions\CheckoutFinalizer;
use AIArmada\Checkout\Actions\HandleCheckoutPaymentCallback;
use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\PaymentCompensationInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Events\CheckoutFailed;
use AIArmada\Checkout\Events\CheckoutStepFailed;
use AIArmada\Checkout\Exceptions\PaymentAttemptExecutionException;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentAttemptManager;
use AIArmada\Checkout\Services\StepExecutor;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Checkout\Support\CheckoutCallbackStatePolicy;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\mock;

/*
|--------------------------------------------------------------------------
| CHK-04: fence thrown errors and step writes, not only returned results
|--------------------------------------------------------------------------
|
| A callback that completes the session while a step is being prepared or is
| in flight must own the outcome. The observing run must not rewrite the
| winner's step map/current-step pointer, must not mark the step failed or
| emit failure events when the gateway then throws, and the service must not
| reopen the completed lifecycle, persist an error, or notify a failure.
|
*/

beforeEach(function (): void {
    Model::clearBootedModels();
});

/**
 * @param  Closure(CheckoutSession): array<string, string>|null  $onValidate
 * @param  Closure(CheckoutSession): StepResult|null  $onHandle
 * @param  Closure(CheckoutSession): bool|null  $onCanSkip
 */
function chk04Step(
    string $identifier,
    array $dependencies = [],
    ?object $tracker = null,
    ?Closure $onValidate = null,
    ?Closure $onHandle = null,
    ?Closure $onCanSkip = null,
): CheckoutStepInterface {
    return new class($identifier, $dependencies, $tracker, $onValidate, $onHandle, $onCanSkip) implements CheckoutStepInterface
    {
        public function __construct(
            private readonly string $identifier,
            private readonly array $dependencies,
            private readonly ?object $tracker,
            private readonly ?Closure $onValidate,
            private readonly ?Closure $onHandle,
            private readonly ?Closure $onCanSkip,
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
            return $this->onValidate !== null ? ($this->onValidate)($session) : [];
        }

        public function handle(CheckoutSession $session): StepResult
        {
            if ($this->tracker !== null) {
                $this->tracker->handled[] = $this->identifier;
            }

            return $this->onHandle !== null
                ? ($this->onHandle)($session)
                : StepResult::success($this->identifier);
        }

        public function canSkip(CheckoutSession $session): bool
        {
            return $this->onCanSkip !== null ? ($this->onCanSkip)($session) : false;
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

function chk04Session(string $cartId, int $total = 1000, array $overrides = []): CheckoutSession
{
    return CheckoutSession::forceCreate(array_merge([
        'cart_id' => $cartId,
        'status' => Pending::class,
        'selected_payment_gateway' => 'chip',
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
function chk04Processor(
    Closure $onCreate,
    ?object $voidTracker = null,
    ?PaymentResult $callbackResult = null,
): PaymentProcessorInterface {
    return new class($onCreate, $voidTracker, $callbackResult) implements PaymentCompensationInterface, PaymentProcessorInterface
    {
        public function __construct(
            private readonly Closure $onCreate,
            private readonly ?object $voidTracker,
            private readonly ?PaymentResult $callbackResult,
        ) {}

        public function getIdentifier(): string
        {
            return 'chip';
        }

        public function getName(): string
        {
            return 'checkout-chk04-gateway';
        }

        public function isAvailable(CheckoutSession $session): bool
        {
            return true;
        }

        public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
        {
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
            return PaymentResult::failed('unused');
        }

        public function checkStatus(string $paymentId): PaymentResult
        {
            return PaymentResult::failed('unused');
        }

        public function voidPayment(string $paymentId, ?string $reason = null): PaymentResult
        {
            if ($this->voidTracker !== null) {
                $this->voidTracker->voided[] = $paymentId;
            }

            return new PaymentResult(status: PaymentStatus::Cancelled, paymentId: $paymentId);
        }
    };
}

function chk04Service(
    PaymentProcessorInterface $processor,
    object $tracker,
    ?CheckoutStepInterface $firstStep = null,
    ?CheckoutStepInterface $paymentStep = null,
): CheckoutService {
    $resolver = mock(PaymentGatewayResolverInterface::class);
    $resolver->shouldReceive('hasGateway')->andReturnTrue();
    $resolver->shouldReceive('resolve')->andReturn($processor);
    $resolver->shouldReceive('getDefaultGateway')->andReturn('chip');

    $registry = new CheckoutStepRegistry;
    $registry->register('calculate_shipping', $firstStep ?? chk04Step('calculate_shipping', [], $tracker));
    $registry->register('process_payment', $paymentStep ?? new ProcessPaymentStep($resolver));
    $registry->register('create_order', chk04Step('create_order', ['process_payment'], $tracker));
    $registry->setOrder(['calculate_shipping', 'process_payment', 'create_order']);

    return new CheckoutService(
        stepRegistry: $registry,
        events: app(Dispatcher::class),
        stepExecutor: new StepExecutor($registry, app(Dispatcher::class)),
        finalizer: new CheckoutFinalizer(app(Dispatcher::class)),
        paymentResolver: $resolver,
    );
}

describe('thrown-error observation fencing', function (): void {
    it('does not begin or handle a step once a callback has completed the session', function (): void {
        Event::fake([CheckoutStepFailed::class, CheckoutFailed::class]);

        $tracker = new class
        {
            public array $executed = [];

            public array $handled = [];

            public array $voided = [];
        };

        $holder = new class
        {
            public ?CheckoutService $service = null;
        };

        $processor = chk04Processor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
                $tracker->executed[] = 'create';

                return PaymentResult::success('unused', amount: 1000);
            },
            voidTracker: $tracker,
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_callback_pre_handle',
                amount: 1000,
                currency: 'MYR',
            ),
        );

        // The callback wins while the payment step is being prepared, before
        // its handle() runs. The guard must observe without a beginStep()
        // write, handle() execution, or a failure event.
        $paymentStep = chk04Step(
            'process_payment',
            ['calculate_shipping'],
            $tracker,
            onValidate: function (CheckoutSession $session) use ($holder): array {
                // A success callback can only arrive for an attempt already in
                // flight; mirror the claim's payment state before delivering it.
                $session->transitionStatus(PaymentProcessing::class);

                (new HandleCheckoutPaymentCallback($holder->service, new CheckoutCallbackStatePolicy))
                    ->handle((string) $session->getKey(), 'success', [
                        'status' => 'paid',
                        'metadata' => ['checkout_attempt' => 1],
                    ]);

                return [];
            },
        );

        $service = chk04Service($processor, $tracker, paymentStep: $paymentStep);
        $holder->service = $service;

        $session = chk04Session('chk04-pre-handle-guard');

        $result = $service->processCheckout($session);
        $fresh = $session->fresh();

        expect($result->success)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(Completed::class)
            ->and($fresh->payment_id)->toBe('pay_callback_pre_handle')
            ->and($tracker->handled)->not->toContain('process_payment')
            ->and(data_get($fresh->step_states, 'process_payment'))->toBe('completed')
            ->and($fresh->current_step)->toBe('create_order')
            ->and($tracker->voided)->toBe([]);

        Event::assertNotDispatched(CheckoutStepFailed::class);
        Event::assertNotDispatched(CheckoutFailed::class);
    });

    it('ignores a thrown gateway error after a callback completed the session', function (): void {
        Event::fake([CheckoutStepFailed::class, CheckoutFailed::class]);

        $tracker = new class
        {
            public array $executed = [];

            public array $handled = [];

            public array $voided = [];
        };

        $holder = new class
        {
            public ?CheckoutService $service = null;
        };

        $processor = chk04Processor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request) use ($tracker, $holder): PaymentResult {
                $tracker->executed[] = 'create';

                (new HandleCheckoutPaymentCallback($holder->service, new CheckoutCallbackStatePolicy))
                    ->handle((string) $session->getKey(), 'success', [
                        'status' => 'paid',
                        'metadata' => ['checkout_attempt' => 1],
                    ]);

                throw new RuntimeException('Gateway connection lost after acceptance');
            },
            voidTracker: $tracker,
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_callback_throw',
                amount: 1000,
                currency: 'MYR',
            ),
        );

        $service = chk04Service($processor, $tracker);
        $holder->service = $service;

        $session = chk04Session('chk04-thrown-after-win-cart');

        $result = $service->processCheckout($session);
        $fresh = $session->fresh();

        expect($result->success)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(Completed::class)
            ->and($fresh->payment_id)->toBe('pay_callback_throw')
            ->and($fresh->error_message)->toBeNull()
            ->and(data_get($fresh->step_states, 'process_payment'))->toBe('completed')
            ->and(data_get($fresh->step_states, 'create_order'))->toBe('completed')
            ->and($tracker->executed)->toBe(['create'])
            ->and($tracker->handled)->toBe(['calculate_shipping', 'create_order'])
            ->and($tracker->voided)->toBe([]);

        Event::assertNotDispatched(CheckoutStepFailed::class);
        Event::assertNotDispatched(CheckoutFailed::class);
    });

    it('treats a failed pipeline phase as authoritative success when a callback won first', function (): void {
        Event::fake([CheckoutStepFailed::class, CheckoutFailed::class]);

        $tracker = new class
        {
            public array $executed = [];

            public array $handled = [];

            public array $voided = [];
        };

        $holder = new class
        {
            public ?CheckoutService $service = null;
        };

        $processor = chk04Processor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
                $tracker->executed[] = 'create';

                return PaymentResult::success('unused', amount: 1000);
            },
            voidTracker: $tracker,
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_callback_phase',
                amount: 1000,
                currency: 'MYR',
            ),
        );

        // The first phase step validates by winning the callback. Its
        // observed result is not a pipeline failure: the service must return
        // the authoritative success instead of marking PaymentFailed and
        // emitting CheckoutFailed over the completed session.
        $firstStep = chk04Step(
            'calculate_shipping',
            [],
            $tracker,
            onValidate: function (CheckoutSession $session) use ($holder): array {
                // A success callback can only arrive for an attempt already in
                // flight; mirror the claim's payment state before delivering it.
                $session->transitionStatus(PaymentProcessing::class);

                (new HandleCheckoutPaymentCallback($holder->service, new CheckoutCallbackStatePolicy))
                    ->handle((string) $session->getKey(), 'success', [
                        'status' => 'paid',
                        'metadata' => ['checkout_attempt' => 1],
                    ]);

                return [];
            },
        );

        $service = chk04Service($processor, $tracker, firstStep: $firstStep);
        $holder->service = $service;

        $session = chk04Session('chk04-phase-observed-cart');

        $result = $service->processCheckout($session);
        $fresh = $session->fresh();

        expect($result->success)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(Completed::class)
            ->and($fresh->payment_id)->toBe('pay_callback_phase')
            ->and($fresh->error_message)->toBeNull()
            ->and($tracker->handled)->not->toContain('calculate_shipping')
            ->and(data_get($fresh->step_states, 'calculate_shipping'))->toBe('pending')
            ->and(data_get($fresh->step_states, 'process_payment'))->toBe('completed')
            ->and($fresh->current_step)->toBe('create_order')
            ->and($tracker->voided)->toBe([]);

        Event::assertNotDispatched(CheckoutStepFailed::class);
        Event::assertNotDispatched(CheckoutFailed::class);
    });
});

describe('execution origin through thrown hooks', function (): void {
    it('keeps the origin captured before CheckoutService preflight canSkip throws after a claim', function (): void {
        Event::fake([CheckoutStepFailed::class, CheckoutFailed::class]);

        $tracker = new class
        {
            public array $handled = [];

            public array $voided = [];
        };
        $manager = app(PaymentAttemptManager::class);
        $processor = chk04Processor(
            onCreate: fn (CheckoutSession $session, PaymentRequest $request): PaymentResult => PaymentResult::success('unused', amount: 1000),
            voidTracker: $tracker,
        );
        $paymentStep = chk04Step(
            'process_payment',
            ['calculate_shipping'],
            $tracker,
            onCanSkip: function (CheckoutSession $session) use ($manager): bool {
                $manager->claim($session, 'chip');

                throw new RuntimeException('skip hook failed after a replacement claim');
            },
        );
        $service = chk04Service($processor, $tracker, paymentStep: $paymentStep);
        $session = chk04Session('chk13-run-can-skip-throw');

        $result = $service->processCheckout($session);
        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($result->metadata['reconciliation'] ?? false)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($fresh->error_message)->toBeNull()
            ->and(data_get($fresh->step_states, 'process_payment'))->toBe(StepStatus::Pending->value)
            ->and($tracker->handled)->toBe([])
            ->and($tracker->voided)->toBe([]);

        Event::assertNotDispatched(CheckoutStepFailed::class);
        Event::assertNotDispatched(CheckoutFailed::class);
    });

    it('keeps the origin captured before validation throws after a same-generation adoption', function (): void {
        Event::fake([CheckoutStepFailed::class, CheckoutFailed::class]);

        $tracker = new class
        {
            public array $handled = [];

            public array $voided = [];
        };
        $manager = app(PaymentAttemptManager::class);
        $processor = chk04Processor(
            onCreate: fn (CheckoutSession $session, PaymentRequest $request): PaymentResult => PaymentResult::success('unused', amount: 1000),
            voidTracker: $tracker,
        );
        $paymentStep = chk04Step(
            'process_payment',
            ['calculate_shipping'],
            $tracker,
            onValidate: function (CheckoutSession $session) use ($manager): array {
                $manager->claim($session, 'chip');

                throw new RuntimeException('validation failed after a replacement claim');
            },
        );
        $service = chk04Service($processor, $tracker, paymentStep: $paymentStep);
        $session = chk04Session('chk13-validation-throw');
        $session->transitionStatus(Processing::class);
        $session->setStepState('calculate_shipping', StepStatus::Completed);
        $initialClaim = $manager->claim($session, 'chip');

        $result = $service->recoverPayment($session);
        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($result->metadata['reconciliation'] ?? false)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($fresh->payment_attempts)->toBe($initialClaim->number)
            ->and(data_get($fresh->payment_data, 'payment_attempt_revision'))->toBeGreaterThan($initialClaim->revision)
            ->and($fresh->error_message)->toBeNull()
            ->and(data_get($fresh->step_states, 'process_payment'))->toBe(StepStatus::Pending->value)
            ->and($tracker->handled)->toBe([])
            ->and($tracker->voided)->toBe([]);

        Event::assertNotDispatched(CheckoutStepFailed::class);
        Event::assertNotDispatched(CheckoutFailed::class);
    });

    it('keeps the origin captured before run-level canSkip throws after a claim', function (): void {
        $manager = app(PaymentAttemptManager::class);
        $session = chk04Session('chk13-run-can-skip-throw');
        $session->transitionStatus(Processing::class);
        $step = chk04Step(
            'calculate_shipping',
            [],
            onCanSkip: function (CheckoutSession $session) use ($manager): bool {
                $manager->claim($session, 'chip');

                throw new RuntimeException('skip hook failed after a replacement claim');
            },
        );
        $registry = new CheckoutStepRegistry;
        $registry->register('calculate_shipping', $step);
        $executor = new StepExecutor($registry, app(Dispatcher::class), $manager);

        try {
            $executor->run($session);
            test()->fail('The canSkip exception must retain its captured execution origin.');
        } catch (PaymentAttemptExecutionException $exception) {
            expect($exception->origin->attempts)->toBe(0)
                ->and($exception->origin->revision)->toBe(0)
                ->and($exception->getOriginal()->getMessage())->toBe('skip hook failed after a replacement claim');
        }

        expect($manager->beginFailureOperation($session->fresh(), $exception->origin))->toBeNull();
    });

    it('keeps the pre-handler origin after a replacement claims during failure notification', function (): void {
        $tracker = new class
        {
            public int $checkoutFailures = 0;
        };
        $manager = app(PaymentAttemptManager::class);
        $session = chk04Session('chk13-nonpayment-handler-throw');
        $session->transitionStatus(Processing::class);
        $step = chk04Step(
            'calculate_shipping',
            [],
            onHandle: fn (): never => throw new RuntimeException('shipping failed'),
        );
        $registry = new CheckoutStepRegistry;
        $registry->register('calculate_shipping', $step);
        $executor = new StepExecutor($registry, app(Dispatcher::class), $manager);

        Event::listen(CheckoutStepFailed::class, function (CheckoutStepFailed $event) use ($manager): void {
            if ($event->stepIdentifier === 'calculate_shipping') {
                $manager->claim($event->session->fresh(), 'chip');
            }
        });
        Event::listen(CheckoutFailed::class, function () use ($tracker): void {
            $tracker->checkoutFailures++;
        });

        try {
            $executor->processStep($session, $step);
            test()->fail('The current handler exception must still reach the service boundary.');
        } catch (PaymentAttemptExecutionException $exception) {
            expect($exception->origin->attempts)->toBe(0)
                ->and($exception->origin->revision)->toBe(0)
                ->and($exception->getOriginal())->toBeInstanceOf(RuntimeException::class)
                ->and($exception->getOriginal()->getMessage())->toBe('shipping failed');
        }

        $fresh = $session->fresh();
        $operation = $manager->beginFailureOperation($fresh, $exception->origin);

        expect($operation)->toBeNull()
            ->and($fresh->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($fresh->error_message)->toBeNull()
            ->and($tracker->checkoutFailures)->toBe(0);
    });
});
