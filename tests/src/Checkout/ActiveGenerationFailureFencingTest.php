<?php

declare(strict_types=1);

use AIArmada\Checkout\Actions\CheckoutFinalizer;
use AIArmada\Checkout\Actions\HandleCheckoutPaymentCallback;
use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\PaymentCompensationInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentAttemptClaim;
use AIArmada\Checkout\Data\PaymentFailureOrigin;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Events\CheckoutFailed;
use AIArmada\Checkout\Events\CheckoutStepFailed;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentAttemptManager;
use AIArmada\Checkout\Services\StepExecutor;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed as PaymentFailedState;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Checkout\Support\CheckoutCallbackStatePolicy;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\mock;

/*
|--------------------------------------------------------------------------
| CHK-04: a stale throw must not fail a newer active generation
|--------------------------------------------------------------------------
|
| The exception path previously fenced only on the persisted status being
| Completed or AwaitingPayment. When attempt 1 resumed with a gateway
| exception after another flow had already claimed attempt 2 (still in
| PaymentProcessing), the stale execution marked the payment step failed,
| compensated the replacement attempt, failed the lifecycle, wrote the
| error, and notified a failure. These regressions bind every failure write
| to the execution's claimed generation under the row lock.
|
*/

beforeEach(function (): void {
    Model::clearBootedModels();
});

function chk04aTrackStep(string $identifier, array $dependencies = [], ?object $tracker = null): CheckoutStepInterface
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

function chk04aSession(string $cartId, int $total = 1000): CheckoutSession
{
    return CheckoutSession::forceCreate([
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
    ]);
}

/**
 * @param  Closure(CheckoutSession, PaymentRequest): PaymentResult  $onCreate
 */
function chk04aProcessor(
    Closure $onCreate,
    object $tracker,
    ?PaymentResult $callbackResult = null,
    ?Closure $onVoid = null,
): PaymentProcessorInterface {
    return new class($onCreate, $tracker, $callbackResult, $onVoid) implements PaymentCompensationInterface, PaymentProcessorInterface
    {
        public function __construct(
            private readonly Closure $onCreate,
            private readonly object $tracker,
            private readonly ?PaymentResult $callbackResult,
            private readonly ?Closure $onVoid,
        ) {}

        public function getIdentifier(): string
        {
            return 'chip';
        }

        public function getName(): string
        {
            return 'checkout-chk04a-gateway';
        }

        public function isAvailable(CheckoutSession $session): bool
        {
            return true;
        }

        public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
        {
            $this->tracker->creates++;
            $this->tracker->attempts[] = $request->metadata['checkout_attempt'] ?? null;

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
            $this->tracker->voided[] = $paymentId;

            if ($this->onVoid !== null) {
                ($this->onVoid)($paymentId);
            }

            return new PaymentResult(status: PaymentStatus::Cancelled, paymentId: $paymentId);
        }
    };
}

function chk04aService(
    PaymentProcessorInterface $processor,
    object $tracker,
    ?object $holder = null,
    bool $hasGateway = true,
): CheckoutService {
    $resolver = mock(PaymentGatewayResolverInterface::class);
    $resolver->shouldReceive('hasGateway')->andReturn($hasGateway);
    $resolver->shouldReceive('resolve')->andReturn($processor);
    $resolver->shouldReceive('getDefaultGateway')->andReturn('chip');

    $registry = new CheckoutStepRegistry;
    $registry->register('calculate_shipping', chk04aTrackStep('calculate_shipping', [], $tracker));
    $registry->register('process_payment', new ProcessPaymentStep($resolver));
    $registry->register('create_order', chk04aTrackStep('create_order', ['process_payment'], $tracker));
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

/**
 * Mirror of the locked replacement phase CheckoutService::recoverPayment()
 * runs for an explicit retry: mark the retry, reset the payment step, and
 * claim the next attempt generation. The replacement's create response is
 * deliberately not recorded, reproducing a retry still in flight.
 */
function chk04aClaimNextGeneration(string $sessionId): PaymentAttemptClaim
{
    $manager = app(PaymentAttemptManager::class);

    return DB::transaction(function () use ($sessionId, $manager): PaymentAttemptClaim {
        $session = CheckoutSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();

        $paymentData = $session->payment_data ?? [];
        $paymentData['retry_requested_for_attempt'] = (int) $session->payment_attempts;

        $session->setStepState('process_payment', StepStatus::Pending);
        $session->persistState([
            'payment_redirect_url' => null,
            'error_message' => null,
            'payment_data' => $paymentData,
        ]);

        if (! $session->status->is(Processing::class)) {
            $session->transitionStatus(Processing::class);
        }

        return $manager->claim($session, 'chip');
    });
}

/**
 * Record the replacement attempt's own response and mirror the winning
 * execution's step write for it: a redirect completes the step, an in-flight
 * non-final outcome leaves it processing.
 */
function chk04aRecordActiveResponse(string $sessionId, PaymentAttemptClaim $claim, PaymentResult $result): void
{
    $manager = app(PaymentAttemptManager::class);
    $session = CheckoutSession::whereKey($sessionId)->firstOrFail();

    $manager->record($session, $claim, $result);

    $fresh = CheckoutSession::whereKey($sessionId)->firstOrFail();
    $fresh->setStepState('process_payment', $result->requiresRedirect() ? StepStatus::Completed : StepStatus::Processing);
}

function chk04aCompensationCount(string $sessionId): int
{
    return count(CheckoutSession::whereKey($sessionId)->firstOrFail()->getCompensationLog());
}

describe('active-generation thrown-error fencing', function (): void {
    it('does not fail or compensate an active replacement attempt claimed before the stale throw', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $tracker = new class
        {
            public int $creates = 0;

            public array $attempts = [];

            public array $executed = [];

            public array $voided = [];

            public int $compensationsBeforeThrow = -1;
        };

        $holder = new class
        {
            public ?CheckoutService $service = null;
        };

        $processor = chk04aProcessor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request) use ($tracker, $holder): PaymentResult {
                $sessionId = (string) $session->getKey();

                // Another flow: a verified failure callback for attempt 1,
                // then an explicit retry claims attempt 2. Attempt 2's create
                // response has not been recorded when attempt 1 resumes.
                (new HandleCheckoutPaymentCallback($holder->service, new CheckoutCallbackStatePolicy))
                    ->handle($sessionId, 'failure', [
                        'payment_id' => 'pay_chk04a_old',
                        'metadata' => ['checkout_attempt' => 1],
                    ]);

                chk04aClaimNextGeneration($sessionId);

                $tracker->compensationsBeforeThrow = chk04aCompensationCount($sessionId);

                throw new RuntimeException('Stale gateway timeout for attempt 1');
            },
            tracker: $tracker,
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_chk04a_unused',
                amount: 1000,
                currency: 'MYR',
            ),
        );

        $service = chk04aService($processor, $tracker, $holder);

        $session = chk04aSession('chk04a-active-generation-cart');
        $result = $service->processCheckout($session);
        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($result->metadata['processing'] ?? null)->toBeTrue()
            ->and($result->metadata['reconciliation'] ?? null)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and((int) $fresh->payment_attempts)->toBe(2)
            ->and($fresh->error_message)->toBeNull()
            ->and($fresh->payment_id)->toBeNull()
            ->and(data_get($fresh->step_states, 'process_payment'))->toBe(StepStatus::Pending->value)
            ->and(data_get($fresh->payment_data, 'payment_attempt.number'))->toBe(2)
            ->and(data_get($fresh->payment_data, 'payment_attempt.idempotency_key'))->toBe($session->getKey() . ':attempt:2')
            ->and(chk04aCompensationCount((string) $session->getKey()))->toBe($tracker->compensationsBeforeThrow)
            ->and($tracker->attempts)->toBe([1])
            ->and($tracker->voided)->toBe([]);

        Event::assertDispatchedTimes(CheckoutFailed::class, 1);
        Event::assertNotDispatched(CheckoutStepFailed::class);
    });

    it('does not fail or void an active replacement attempt that already recorded a response', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $tracker = new class
        {
            public int $creates = 0;

            public array $attempts = [];

            public array $executed = [];

            public array $voided = [];

            public int $compensationsBeforeThrow = -1;
        };

        $holder = new class
        {
            public ?CheckoutService $service = null;
        };

        $processor = chk04aProcessor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request) use ($tracker, $holder): PaymentResult {
                $sessionId = (string) $session->getKey();

                (new HandleCheckoutPaymentCallback($holder->service, new CheckoutCallbackStatePolicy))
                    ->handle($sessionId, 'failure', [
                        'payment_id' => 'pay_chk04a_old',
                        'metadata' => ['checkout_attempt' => 1],
                    ]);

                $claim = chk04aClaimNextGeneration($sessionId);
                chk04aRecordActiveResponse($sessionId, $claim, PaymentResult::processing('pay_chk04a_active', 'chip'));

                $tracker->compensationsBeforeThrow = chk04aCompensationCount($sessionId);

                throw new RuntimeException('Stale gateway timeout for attempt 1');
            },
            tracker: $tracker,
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_chk04a_unused',
                amount: 1000,
                currency: 'MYR',
            ),
        );

        $service = chk04aService($processor, $tracker, $holder);

        $session = chk04aSession('chk04a-active-response-cart');
        $result = $service->processCheckout($session);
        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($result->metadata['processing'] ?? null)->toBeTrue()
            ->and($result->metadata['reconciliation'] ?? null)->toBeNull()
            ->and($fresh->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and((int) $fresh->payment_attempts)->toBe(2)
            ->and($fresh->payment_id)->toBe('pay_chk04a_active')
            ->and($fresh->error_message)->toBeNull()
            ->and(data_get($fresh->step_states, 'process_payment'))->toBe(StepStatus::Processing->value)
            ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.status'))->toBe(PaymentStatus::Processing->value)
            ->and(chk04aCompensationCount((string) $session->getKey()))->toBe($tracker->compensationsBeforeThrow)
            ->and($tracker->attempts)->toBe([1])
            ->and($tracker->voided)->toBe([]);

        Event::assertDispatchedTimes(CheckoutFailed::class, 1);
        Event::assertNotDispatched(CheckoutStepFailed::class);
    });

    it('does not fail an attempt that already redirected after the stale throw', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $tracker = new class
        {
            public int $creates = 0;

            public array $attempts = [];

            public array $executed = [];

            public array $voided = [];

            public int $compensationsBeforeThrow = -1;
        };

        $holder = new class
        {
            public ?CheckoutService $service = null;
        };

        $processor = chk04aProcessor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request) use ($tracker, $holder): PaymentResult {
                $sessionId = (string) $session->getKey();

                (new HandleCheckoutPaymentCallback($holder->service, new CheckoutCallbackStatePolicy))
                    ->handle($sessionId, 'failure', [
                        'payment_id' => 'pay_chk04a_old',
                        'metadata' => ['checkout_attempt' => 1],
                    ]);

                $claim = chk04aClaimNextGeneration($sessionId);
                chk04aRecordActiveResponse(
                    $sessionId,
                    $claim,
                    PaymentResult::pending('pay_chk04a_redirect', 'https://gateway.example.test/pay/chk04a-attempt-2'),
                );

                $tracker->compensationsBeforeThrow = chk04aCompensationCount($sessionId);

                throw new RuntimeException('Stale gateway timeout for attempt 1');
            },
            tracker: $tracker,
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_chk04a_unused',
                amount: 1000,
                currency: 'MYR',
            ),
        );

        $service = chk04aService($processor, $tracker, $holder);

        $session = chk04aSession('chk04a-active-redirect-cart');
        $result = $service->processCheckout($session);
        $fresh = $session->fresh();

        expect($result->requiresRedirect())->toBeTrue()
            ->and($result->redirectUrl)->toBe('https://gateway.example.test/pay/chk04a-attempt-2')
            ->and($fresh->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and((int) $fresh->payment_attempts)->toBe(2)
            ->and($fresh->payment_id)->toBe('pay_chk04a_redirect')
            ->and($fresh->payment_redirect_url)->toBe('https://gateway.example.test/pay/chk04a-attempt-2')
            ->and($fresh->error_message)->toBeNull()
            ->and(data_get($fresh->step_states, 'process_payment'))->toBe(StepStatus::Completed->value)
            ->and(chk04aCompensationCount((string) $session->getKey()))->toBe($tracker->compensationsBeforeThrow)
            ->and($tracker->attempts)->toBe([1])
            ->and($tracker->voided)->toBe([]);

        Event::assertDispatchedTimes(CheckoutFailed::class, 1);
        Event::assertNotDispatched(CheckoutStepFailed::class);
    });

    it('still fails the checkout when the execution owns the attempt and the gateway throws', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $tracker = new class
        {
            public int $creates = 0;

            public array $attempts = [];

            public array $executed = [];

            public array $voided = [];
        };

        $processor = chk04aProcessor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request): PaymentResult {
                throw new RuntimeException('Owner gateway timeout');
            },
            tracker: $tracker,
        );

        $service = chk04aService($processor, $tracker);

        $session = chk04aSession('chk04a-owner-failure-cart');

        expect(fn (): mixed => $service->processCheckout($session))
            ->toThrow(RuntimeException::class, 'Owner gateway timeout');

        $fresh = $session->fresh();

        expect($fresh->status)->toBeInstanceOf(PaymentFailedState::class)
            ->and((int) $fresh->payment_attempts)->toBe(1)
            ->and($fresh->error_message)->toBe('An error occurred during checkout. Please try again.')
            ->and(data_get($fresh->step_states, 'process_payment'))->toBe(StepStatus::RolledBack->value)
            ->and($fresh->getCompensationLog())->not->toBe([])
            ->and($tracker->attempts)->toBe([1])
            ->and($tracker->voided)->toBe([]);

        Event::assertDispatchedTimes(CheckoutStepFailed::class, 1);
        Event::assertDispatchedTimes(CheckoutFailed::class, 1);
    });
});

/*
|--------------------------------------------------------------------------
| CHK-04 residual: the interval after the ownership check releases its lock
|--------------------------------------------------------------------------
|
| A single ownership check before compensation still leaves a window while
| the gateway is being voided/refunded. A replacement claim or a completed
| outcome that lands inside that window must turn every remaining
| compensation-log, step-state, lifecycle, error, and notification write
| into an observation. These regressions run the race from inside a step's
| compensation, after the operation token was issued.
|
*/

describe('failure-operation interval fencing', function (): void {
    it('stops stale compensation writes when a replacement claim lands mid-compensation', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $tracker = new class
        {
            public int $creates = 0;

            public array $attempts = [];

            public array $executed = [];

            public array $voided = [];

            public ?string $sessionId = null;
        };

        // The replacement claim lands while the stale run is voiding attempt
        // 1's gateway payment: after the operation token was issued and the
        // lock released, but before the compensation result is recorded.
        $processor = chk04aProcessor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
                $tracker->sessionId = (string) $session->getKey();

                return PaymentResult::failed('Card declined', paymentId: 'pay_chk04a_stale_failure');
            },
            tracker: $tracker,
            onVoid: function (string $paymentId) use ($tracker): void {
                chk04aClaimNextGeneration((string) $tracker->sessionId);
            },
        );

        $service = chk04aService($processor, $tracker);

        $session = chk04aSession('chk04a-mid-compensation-claim');
        $result = $service->processCheckout($session);
        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($result->metadata['processing'] ?? null)->toBeTrue()
            ->and($result->metadata['reconciliation'] ?? null)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and((int) $fresh->payment_attempts)->toBe(2)
            ->and(data_get($fresh->payment_data, 'payment_attempt.number'))->toBe(2)
            ->and($fresh->payment_id)->toBeNull()
            ->and($fresh->error_message)->toBeNull()
            ->and($fresh->getCompensationLog())->toBe([])
            ->and(data_get($fresh->step_states, 'process_payment'))->toBe(StepStatus::Pending->value)
            ->and(data_get($fresh->payment_data, 'superseded_failure_compensations.0.attempts'))->toBe(1)
            ->and(data_get($fresh->payment_data, 'superseded_failure_compensations.0.step_identifier'))->toBe('process_payment')
            ->and($tracker->voided)->toBe(['pay_chk04a_stale_failure']);

        Event::assertNotDispatched(CheckoutFailed::class);
    });

    it('observes a completed outcome instead of finishing a stale failure during compensation', function (): void {
        config()->set('checkout.payment.retry_limit', 3);
        Event::fake([CheckoutFailed::class, CheckoutStepFailed::class]);

        $tracker = new class
        {
            public int $creates = 0;

            public array $attempts = [];

            public array $executed = [];

            public array $voided = [];

            public ?string $sessionId = null;

            public ?CheckoutService $service = null;
        };

        // The verified success callback lands while the stale run is voiding
        // the failed attempt's payment. The completion owns the outcome; the
        // stale operation must observe it instead of writing a failure.
        $processor = chk04aProcessor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
                $tracker->sessionId = (string) $session->getKey();

                return PaymentResult::failed('Card declined', paymentId: 'pay_chk04a_race_paid');
            },
            tracker: $tracker,
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_chk04a_race_paid',
                amount: 1000,
                currency: 'MYR',
            ),
            onVoid: function (string $paymentId) use ($tracker): void {
                (new HandleCheckoutPaymentCallback($tracker->service, new CheckoutCallbackStatePolicy))
                    ->handle((string) $tracker->sessionId, 'success', [
                        'id' => 'pay_chk04a_race_paid',
                    ]);
            },
        );

        $service = chk04aService($processor, $tracker, $tracker);

        $session = chk04aSession('chk04a-mid-compensation-completion');
        $result = $service->processCheckout($session);
        $fresh = $session->fresh();

        expect($result->success)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(Completed::class)
            ->and($fresh->error_message)->toBeNull()
            ->and($fresh->getCompensationLog())->toBe([])
            ->and(data_get($fresh->step_states, 'process_payment'))->not->toBe(StepStatus::RolledBack->value)
            ->and($tracker->voided)->toBe(['pay_chk04a_race_paid']);

        Event::assertNotDispatched(CheckoutFailed::class);
    });
});

/*
|--------------------------------------------------------------------------
| CHK-04 residual: failure authority is execution-bound
|--------------------------------------------------------------------------
|
| A returned failure, validation failure, or thrown failure belongs to the
| execution that observed it. A newer claim or an authoritative outcome on
| the same generation revokes that execution's authority before it can
| compensate or finish the checkout.
|
*/

describe('execution-bound failure authority', function (): void {
    it('does not fail or void attempt 2 from attempt 1 returned failure', function (): void {
        config()->set('checkout.payment.retry_limit', 3);

        $tracker = new class
        {
            public int $creates = 0;

            public array $attempts = [];

            public array $voided = [];

            public ?string $sessionId = null;
        };

        $failedEvents = new class
        {
            public int $count = 0;
        };

        Event::listen(CheckoutFailed::class, function () use ($failedEvents): void {
            $failedEvents->count++;
        });

        Event::listen(CheckoutStepFailed::class, function (CheckoutStepFailed $event) use ($tracker): void {
            if ($event->stepIdentifier !== 'process_payment' || $tracker->sessionId === null) {
                return;
            }

            $claim = chk04aClaimNextGeneration($tracker->sessionId);
            chk04aRecordActiveResponse(
                $tracker->sessionId,
                $claim,
                PaymentResult::processing('pay_replacement_two', 'chip'),
            );
        });

        $processor = chk04aProcessor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
                $tracker->sessionId = (string) $session->getKey();

                return PaymentResult::failed('Attempt 1 declined');
            },
            tracker: $tracker,
        );

        $service = chk04aService($processor, $tracker);

        $session = chk04aSession('chk04a-returned-failure-newer-claim');
        $result = $service->processCheckout($session);
        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($result->metadata['processing'] ?? null)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and((int) $fresh->payment_attempts)->toBe(2)
            ->and($fresh->payment_id)->toBe('pay_replacement_two')
            ->and($fresh->error_message)->toBeNull()
            ->and($fresh->getCompensationLog())->toBe([])
            ->and(data_get($fresh->step_states, 'process_payment'))->toBe(StepStatus::Processing->value)
            ->and($tracker->voided)->toBe([])
            ->and($failedEvents->count)->toBe(0);
    });

    it('does not fail or void a claim created after a validation failure', function (): void {
        config()->set('checkout.payment.retry_limit', 3);

        $tracker = new class
        {
            public int $creates = 0;

            public array $attempts = [];

            public array $voided = [];

            public ?string $sessionId = null;
        };

        $failedEvents = new class
        {
            public int $count = 0;
        };

        Event::listen(CheckoutFailed::class, function () use ($failedEvents): void {
            $failedEvents->count++;
        });

        Event::listen(CheckoutStepFailed::class, function (CheckoutStepFailed $event): void {
            if ($event->stepIdentifier !== 'process_payment') {
                return;
            }

            $sessionId = (string) $event->session->getKey();
            $claim = chk04aClaimNextGeneration($sessionId);
            chk04aRecordActiveResponse(
                $sessionId,
                $claim,
                PaymentResult::processing('pay_validation_replacement', 'chip'),
            );
        });

        $processor = chk04aProcessor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
                $tracker->sessionId = (string) $session->getKey();

                return PaymentResult::failed('unexpected');
            },
            tracker: $tracker,
        );

        $service = chk04aService($processor, $tracker, hasGateway: false);

        $session = chk04aSession('chk04a-validation-newer-claim');
        $result = $service->processCheckout($session);
        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($result->metadata['processing'] ?? null)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and((int) $fresh->payment_attempts)->toBe(1)
            ->and($fresh->payment_id)->toBe('pay_validation_replacement')
            ->and($fresh->error_message)->toBeNull()
            ->and($fresh->getCompensationLog())->toBe([])
            ->and($tracker->voided)->toBe([])
            ->and($failedEvents->count)->toBe(0);
    });

    it('observes a same-generation recovery instead of failing the recovered payment', function (): void {
        config()->set('checkout.payment.retry_limit', 3);

        $tracker = new class
        {
            public int $creates = 0;

            public array $attempts = [];

            public array $voided = [];

            public ?string $sessionId = null;
        };

        $failedEvents = new class
        {
            public int $count = 0;
        };

        Event::listen(CheckoutFailed::class, function () use ($failedEvents): void {
            $failedEvents->count++;
        });

        Event::listen(CheckoutStepFailed::class, function (CheckoutStepFailed $event) use ($tracker): void {
            if ($event->stepIdentifier !== 'process_payment' || $tracker->sessionId === null) {
                return;
            }

            $manager = app(PaymentAttemptManager::class);
            $session = CheckoutSession::whereKey($tracker->sessionId)->firstOrFail();
            $paymentData = $session->payment_data ?? [];
            $paymentData['retry_requested_for_attempt'] = (int) $session->payment_attempts;
            $session->persistState(['payment_data' => $paymentData]);

            $claim = $manager->claim($session, 'chip');
            $manager->record($session, $claim, new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_recovered_same_generation',
                amount: 1000,
                currency: 'MYR',
            ));
        });

        $processor = chk04aProcessor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
                $tracker->sessionId = (string) $session->getKey();

                throw new RuntimeException('Attempt 1 timed out');
            },
            tracker: $tracker,
        );

        $service = chk04aService($processor, $tracker);

        $session = chk04aSession('chk04a-same-generation-recovery');
        $result = $service->processCheckout($session);
        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($fresh->status)->toBeInstanceOf(Processing::class)
            ->and((int) $fresh->payment_attempts)->toBe(1)
            ->and($fresh->payment_id)->toBe('pay_recovered_same_generation')
            ->and($fresh->error_message)->toBeNull()
            ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.status'))->toBe(PaymentStatus::Completed->value)
            ->and($fresh->getCompensationLog())->toBe([])
            ->and($tracker->voided)->toBe([])
            ->and($failedEvents->count)->toBe(0);
    });

    it('revokes a failure operation when the same generation successfully recovers before finish', function (): void {
        $manager = app(PaymentAttemptManager::class);

        $session = chk04aSession('chk04a-operation-revoked');
        $session->transitionStatus(Processing::class);
        $claim = $manager->claim($session, 'chip');
        $origin = PaymentFailureOrigin::fromClaim($claim);
        $operation = $manager->beginFailureOperation($session, $origin);

        expect($operation)->not->toBeNull();

        $recovery = CheckoutSession::whereKey($session->getKey())->firstOrFail();
        $paymentData = $recovery->payment_data ?? [];
        $paymentData['retry_requested_for_attempt'] = (int) $recovery->payment_attempts;
        $recovery->persistState(['payment_data' => $paymentData]);

        $adopted = $manager->claim($recovery, 'chip');
        $manager->record($recovery, $adopted, new PaymentResult(
            status: PaymentStatus::Completed,
            paymentId: 'pay_recovered_before_finish',
            amount: 1000,
            currency: 'MYR',
        ));

        $tracker = new class
        {
            public array $compensated = [];
        };

        $step = new class($tracker) implements CheckoutStepInterface
        {
            public function __construct(private readonly object $tracker) {}

            public function getIdentifier(): string
            {
                return 'inventory';
            }

            public function getName(): string
            {
                return 'inventory';
            }

            /** @return array<string> */
            public function getDependencies(): array
            {
                return [];
            }

            public function validate(CheckoutSession $session): array
            {
                return [];
            }

            public function handle(CheckoutSession $session): StepResult
            {
                return StepResult::success('inventory');
            }

            public function canSkip(CheckoutSession $session): bool
            {
                return false;
            }

            public function compensate(CheckoutSession $session): StepResult
            {
                $this->tracker->compensated[] = 'inventory';

                return StepResult::compensated('inventory');
            }
        };

        $registry = new CheckoutStepRegistry;
        $registry->register('inventory', $step);
        $registry->setOrder(['inventory']);

        $executor = new StepExecutor($registry, app(Dispatcher::class), $manager);

        $session->setStepState('inventory', StepStatus::Completed);
        $results = $executor->compensate($session, $operation);

        expect($results)->toBe([])
            ->and($tracker->compensated)->toBe([])
            ->and($manager->finishFailureOperationIfOwned($session, $operation, 'stale failure'))->toBeFalse();

        $fresh = $session->fresh();

        expect($fresh->status)->toBeInstanceOf(Processing::class)
            ->and($fresh->error_message)->toBeNull()
            ->and($fresh->payment_id)->toBe('pay_recovered_before_finish')
            ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.status'))->toBe(PaymentStatus::Completed->value)
            ->and($fresh->getCompensationLog())->toBe([]);
    });

    it('refuses failure authority when recovery already recorded an outcome for the generation', function (): void {
        $manager = app(PaymentAttemptManager::class);

        $session = chk04aSession('chk04a-operation-after-recovery');
        $session->transitionStatus(Processing::class);
        $claim = $manager->claim($session, 'chip');
        $origin = PaymentFailureOrigin::fromClaim($claim);

        $recovery = CheckoutSession::whereKey($session->getKey())->firstOrFail();
        $paymentData = $recovery->payment_data ?? [];
        $paymentData['retry_requested_for_attempt'] = (int) $recovery->payment_attempts;
        $recovery->persistState(['payment_data' => $paymentData]);

        $adopted = $manager->claim($recovery, 'chip');
        $manager->record($recovery, $adopted, new PaymentResult(
            status: PaymentStatus::Completed,
            paymentId: 'pay_recovered_first',
            amount: 1000,
            currency: 'MYR',
        ));

        expect($manager->beginFailureOperation($session, $origin))->toBeNull();

        $fresh = $session->fresh();

        expect($fresh->status)->toBeInstanceOf(Processing::class)
            ->and($fresh->error_message)->toBeNull()
            ->and($fresh->payment_id)->toBe('pay_recovered_first')
            ->and($fresh->getCompensationLog())->toBe([]);
    });
});
