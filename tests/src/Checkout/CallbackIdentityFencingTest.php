<?php

declare(strict_types=1);

use AIArmada\Checkout\Actions\CheckoutFinalizer;
use AIArmada\Checkout\Actions\HandleCheckoutPaymentCallback;
use AIArmada\Checkout\Actions\ProcessCheckoutPaymentNotification;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\PaymentCompensationInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Events\CheckoutCancelled;
use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Checkout\Events\CheckoutFailed;
use AIArmada\Checkout\Events\CheckoutPaymentCompleted;
use AIArmada\Checkout\Http\Controllers\PaymentCallbackController;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentAttemptManager;
use AIArmada\Checkout\Services\StepExecutor;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Checkout\Support\CheckoutPaymentReference;
use AIArmada\Checkout\Support\ChipPurchasePayloadBuilder;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

use function Pest\Laravel\mock;

/*
|--------------------------------------------------------------------------
| CHK-07: reject callbacks belonging to superseded attempts
|--------------------------------------------------------------------------
|
| A callback/webhook outcome is bound to the attempt generation that owns
| the session. A superseded generation's success, failure, or cancel must
| not overwrite the current payment identity, compensate the winner, or
| create an order. A callback for the current attempt (or a callback that
| arrives before the create response) must still complete, a prior
| attempt's redirect token must not authorize the new attempt, and
| redelivered callbacks must stay idempotent.
|
*/

beforeEach(function (): void {
    Model::clearBootedModels();
});

/**
 * @param  Closure(CheckoutSession): StepResult|null  $onHandle
 */
function chk07Step(string $identifier, array $dependencies = [], ?Closure $onHandle = null): CheckoutStepInterface
{
    return new class($identifier, $dependencies, $onHandle) implements CheckoutStepInterface
    {
        public function __construct(
            private readonly string $identifier,
            private readonly array $dependencies,
            private readonly ?Closure $onHandle,
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
            return $this->onHandle !== null
                ? ($this->onHandle)($session)
                : StepResult::success($this->identifier);
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

function chk07Session(string $cartId, array $overrides = []): CheckoutSession
{
    return CheckoutSession::forceCreate(array_merge([
        'cart_id' => $cartId,
        'status' => Processing::class,
        'selected_payment_gateway' => 'chip',
        'grand_total' => 1000,
        'currency' => 'MYR',
        'step_states' => [
            'calculate_shipping' => 'completed',
            'process_payment' => 'pending',
            'create_order' => 'pending',
        ],
    ], $overrides));
}

function chk07Processor(
    ?PaymentResult $callbackResult = null,
    ?PaymentResult $statusResult = null,
    ?object $tracker = null,
): PaymentProcessorInterface {
    return new class($callbackResult, $statusResult, $tracker) implements PaymentCompensationInterface, PaymentProcessorInterface
    {
        public function __construct(
            private readonly ?PaymentResult $callbackResult,
            private readonly ?PaymentResult $statusResult,
            private readonly ?object $tracker,
        ) {}

        public function getIdentifier(): string
        {
            return 'chip';
        }

        public function getName(): string
        {
            return 'checkout-chk07-gateway';
        }

        public function isAvailable(CheckoutSession $session): bool
        {
            return true;
        }

        public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
        {
            return PaymentResult::failed('unused');
        }

        public function handleCallback(array $payload): PaymentResult
        {
            if ($this->tracker !== null) {
                $this->tracker->callbackCalls++;
            }

            return $this->callbackResult ?? PaymentResult::failed('unused');
        }

        public function getRedirectUrl(CheckoutSession $session): ?string
        {
            return null;
        }

        public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
        {
            if ($this->tracker !== null) {
                $this->tracker->refunded[] = $paymentId;
            }

            return PaymentResult::failed('unused');
        }

        public function checkStatus(string $paymentId): PaymentResult
        {
            if ($this->tracker !== null) {
                $this->tracker->statusCalls++;
            }

            return $this->statusResult ?? PaymentResult::failed('unused');
        }

        public function voidPayment(string $paymentId, ?string $reason = null): PaymentResult
        {
            if ($this->tracker !== null) {
                $this->tracker->voided[] = $paymentId;
            }

            return new PaymentResult(status: PaymentStatus::Cancelled, paymentId: $paymentId);
        }
    };
}

function chk07Service(PaymentProcessorInterface $processor, string $orderId, ?object $orderTracker = null): CheckoutService
{
    $resolver = mock(PaymentGatewayResolverInterface::class);
    $resolver->shouldReceive('hasGateway')->andReturnTrue();
    $resolver->shouldReceive('resolve')->andReturn($processor);
    $resolver->shouldReceive('getDefaultGateway')->andReturn('chip');

    $registry = new CheckoutStepRegistry;
    $registry->register('calculate_shipping', chk07Step('calculate_shipping'));
    $registry->register('process_payment', new ProcessPaymentStep($resolver));
    $registry->register('create_order', chk07Step(
        'create_order',
        ['process_payment'],
        function (CheckoutSession $session) use ($orderId, $orderTracker): StepResult {
            if ($orderTracker !== null && property_exists($orderTracker, 'orders')) {
                $orderTracker->orders++;
            }

            $session->persistState(['order_id' => $orderId]);

            return StepResult::success('create_order');
        },
    ));
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
 * Build generation 1 (provider-one, definitively failed) and generation 2
 * (provider-two, pending `pay_gen2` with the same amount) through the real
 * claim/record path, ending with `pay_gen2` as the current payment.
 */
function chk07TwoGenerations(CheckoutSession $session, string $currentProvider = 'provider-two'): void
{
    $manager = app(PaymentAttemptManager::class);

    $claimOne = $manager->claim($session, 'chip');

    $manager->record($session, $claimOne, new PaymentResult(
        status: PaymentStatus::Failed,
        paymentId: 'pay_gen1',
        amount: 1000,
        currency: 'MYR',
        provider: 'provider-one',
        message: 'Declined',
    ));

    $paymentData = $session->payment_data ?? [];
    $paymentData['provider'] = $currentProvider;
    $session->persistState(['payment_data' => $paymentData]);

    $claimTwo = $manager->claim($session, 'chip');

    $manager->record($session, $claimTwo, new PaymentResult(
        status: PaymentStatus::Pending,
        paymentId: 'pay_gen2',
        redirectUrl: 'https://gateway.example.test/pay/gen2',
        amount: 1000,
        currency: 'MYR',
        provider: $currentProvider,
    ));
}

describe('callback identity fencing', function (): void {
    it('rejects a superseded generation-1 success callback while generation 2 is current', function (): void {
        $session = chk07Session('chk07-superseded-success');
        chk07TwoGenerations($session);

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_gen1',
                amount: 1000,
                currency: 'MYR',
                provider: 'provider-one',
            ),
            tracker: $tracker,
        );

        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class, CheckoutFailed::class]);

        $service = chk07Service($processor, (string) Str::uuid());

        $before = $session->fresh();
        expect($before->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($before->payment_id)->toBe('pay_gen2')
            ->and(data_get($before->payment_data, 'provider'))->toBe('provider-two')
            ->and(data_get($before->payment_data, 'amount'))->toBe(1000)
            ->and(data_get($before->payment_data, 'payment_attempt_outcome.number'))->toBe(2);

        $result = $service->handlePaymentCallback($session, 'success', ['status' => 'paid']);

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Payment identity mismatch');

        $fresh = $session->fresh();

        expect($fresh->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($fresh->payment_id)->toBe('pay_gen2')
            ->and($fresh->order_id)->toBeNull()
            ->and($fresh->error_message)->toBeNull()
            ->and(data_get($fresh->payment_data, 'verification_status'))->toBeNull()
            ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.number'))->toBe(2)
            ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.status'))->toBe(PaymentStatus::Pending->value)
            ->and(data_get($fresh->payment_data, 'obsolete_attempt_receipts.0.payment_id'))->toBe('pay_gen1')
            ->and(data_get($fresh->payment_data, 'obsolete_attempt_receipts.0.provider'))->toBe('provider-one')
            ->and($tracker->voided)->toBe([])
            ->and($tracker->refunded)->toBe([]);

        Event::assertNotDispatched(CheckoutPaymentCompleted::class);
        Event::assertNotDispatched(CheckoutCompleted::class);
        Event::assertNotDispatched(CheckoutFailed::class);
    });

    it('rejects a superseded generation-1 failure callback while generation 2 is current', function (): void {
        $session = chk07Session('chk07-superseded-failure');
        chk07TwoGenerations($session);

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(tracker: $tracker);

        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class, CheckoutFailed::class]);

        $service = chk07Service($processor, (string) Str::uuid());

        $result = $service->handlePaymentCallback($session, 'failure', [
            'payment_id' => 'pay_gen1',
            'status' => 'failed',
        ]);

        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Payment identity mismatch')
            ->and($fresh->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($fresh->payment_id)->toBe('pay_gen2')
            ->and($fresh->error_message)->toBeNull()
            ->and($fresh->order_id)->toBeNull()
            ->and($tracker->voided)->toBe([])
            ->and($tracker->refunded)->toBe([]);

        Event::assertNotDispatched(CheckoutFailed::class);
    });

    it('rejects a superseded generation-1 cancel callback while generation 2 is current', function (): void {
        $session = chk07Session('chk07-superseded-cancel');
        chk07TwoGenerations($session);

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(tracker: $tracker);

        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class, CheckoutFailed::class, CheckoutCancelled::class]);

        $service = chk07Service($processor, (string) Str::uuid());

        $result = $service->handlePaymentCallback($session, 'cancel', [
            'payment_id' => 'pay_gen1',
        ]);

        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Payment identity mismatch')
            ->and($fresh->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($fresh->payment_id)->toBe('pay_gen2')
            ->and($tracker->voided)->toBe([])
            ->and($tracker->refunded)->toBe([]);

        Event::assertNotDispatched(CheckoutCancelled::class);
    });

    it('rejects a stale status-poll payment that is not the current payment', function (): void {
        $session = chk07Session('chk07-stale-status-poll');
        chk07TwoGenerations($session);

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(
            statusResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_stale_poll',
                amount: 1000,
                currency: 'MYR',
                provider: 'provider-one',
            ),
            tracker: $tracker,
        );

        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class, CheckoutFailed::class]);

        $service = chk07Service($processor, (string) Str::uuid());

        $result = $service->handlePaymentCallback($session, 'success', []);

        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Payment identity mismatch')
            ->and($tracker->statusCalls)->toBe(1)
            ->and($fresh->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($fresh->payment_id)->toBe('pay_gen2')
            ->and($fresh->error_message)->toBeNull()
            ->and($fresh->order_id)->toBeNull()
            ->and(data_get($fresh->payment_data, 'verification_status'))->toBeNull()
            ->and($tracker->voided)->toBe([])
            ->and($tracker->refunded)->toBe([]);

        Event::assertNotDispatched(CheckoutPaymentCompleted::class);
        Event::assertNotDispatched(CheckoutCompleted::class);
    });

    it('accepts a callback before the create response using the frozen attempt identity', function (): void {
        $session = chk07Session('chk07-callback-before-create');

        $manager = app(PaymentAttemptManager::class);
        $claim = $manager->claim($session, 'chip');

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_before_create',
                amount: 1000,
                currency: 'MYR',
                provider: 'provider-one',
            ),
            tracker: $tracker,
        );

        $orderId = (string) Str::uuid();

        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class, CheckoutFailed::class]);

        $service = chk07Service($processor, $orderId);

        $pending = $session->fresh();

        expect($pending->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($pending->payment_id)->toBeNull()
            ->and(data_get($pending->payment_data, 'payment_attempt.number'))->toBe(1);

        $result = $service->handlePaymentCallback($pending, 'success', [
            'status' => 'paid',
            'metadata' => ['checkout_attempt' => 1],
        ]);

        $fresh = $session->fresh();

        expect($result->success)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(Completed::class)
            ->and($fresh->payment_id)->toBe('pay_before_create')
            ->and($fresh->order_id)->toBe($orderId)
            ->and(data_get($fresh->payment_data, 'verification_status'))->toBe(PaymentStatus::Completed->value)
            ->and(data_get($fresh->payment_data, 'payment_attempt.number'))->toBe(1)
            ->and(data_get($fresh->step_states, 'process_payment'))->toBe('completed')
            ->and(data_get($fresh->step_states, 'create_order'))->toBe('completed')
            ->and($tracker->callbackCalls)->toBe(1)
            ->and($tracker->voided)->toBe([])
            ->and($tracker->refunded)->toBe([]);

        Event::assertDispatchedTimes(CheckoutPaymentCompleted::class, 1);
        Event::assertDispatchedTimes(CheckoutCompleted::class, 1);
    });

    it('accepts a correctly attributed current success callback', function (): void {
        $session = chk07Session('chk07-current-success');
        chk07TwoGenerations($session);

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_gen2',
                amount: 1000,
                currency: 'MYR',
                provider: 'provider-two',
            ),
            tracker: $tracker,
        );

        $orderId = (string) Str::uuid();

        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class, CheckoutFailed::class]);

        $service = chk07Service($processor, $orderId);

        $result = $service->handlePaymentCallback($session, 'success', ['status' => 'paid']);

        $fresh = $session->fresh();

        expect($result->success)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(Completed::class)
            ->and($fresh->payment_id)->toBe('pay_gen2')
            ->and($fresh->order_id)->toBe($orderId)
            ->and((int) $fresh->payment_attempts)->toBe(2)
            ->and(data_get($fresh->payment_data, 'verification_status'))->toBe(PaymentStatus::Completed->value)
            ->and($tracker->voided)->toBe([])
            ->and($tracker->refunded)->toBe([]);

        Event::assertDispatchedTimes(CheckoutCompleted::class, 1);
    });

    it('keeps a completed callback idempotent on redelivery', function (): void {
        $session = chk07Session('chk07-duplicate-callback');

        $manager = app(PaymentAttemptManager::class);
        $manager->claim($session, 'chip');

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_duplicate',
                amount: 1000,
                currency: 'MYR',
                provider: 'provider-one',
            ),
            tracker: $tracker,
        );

        $orderId = (string) Str::uuid();

        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class, CheckoutFailed::class]);

        $service = chk07Service($processor, $orderId);
        app()->instance(CheckoutServiceInterface::class, $service);

        $action = app(HandleCheckoutPaymentCallback::class);

        $first = $action->handle((string) $session->getKey(), 'success', [
            'status' => 'paid',
            'metadata' => ['checkout_attempt' => 1],
        ]);
        $second = $action->handle((string) $session->getKey(), 'success', [
            'status' => 'paid',
            'metadata' => ['checkout_attempt' => 1],
        ]);

        $fresh = $session->fresh();

        expect($first->result?->success)->toBeTrue()
            ->and($second->alreadyCompleted)->toBeTrue()
            ->and($second->result)->toBeNull()
            ->and($fresh->status)->toBeInstanceOf(Completed::class)
            ->and($fresh->payment_id)->toBe('pay_duplicate')
            ->and($fresh->order_id)->toBe($orderId)
            ->and($tracker->callbackCalls)->toBe(1);

        Event::assertDispatchedTimes(CheckoutPaymentCompleted::class, 1);
        Event::assertDispatchedTimes(CheckoutCompleted::class, 1);
    });

    it('rotates the callback token per attempt and rejects a prior attempt token', function (): void {
        config()->set('checkout.payment.callback_token_ttl', 3600);
        config()->set('checkout.redirects.success', '/orders/{session_id}');
        config()->set('checkout.redirects.failure', '/checkout/failed');

        $session = chk07Session('chk07-token-rotation');

        $manager = app(PaymentAttemptManager::class);
        $claimOne = $manager->claim($session, 'chip');

        $tokenOne = data_get($session->payment_data, 'callback_token');

        $manager->record($session, $claimOne, new PaymentResult(
            status: PaymentStatus::Failed,
            paymentId: 'pay_gen1',
            amount: 1000,
            currency: 'MYR',
            provider: 'provider-one',
            message: 'Declined',
        ));

        $paymentData = $session->payment_data ?? [];
        $paymentData['provider'] = 'provider-two';
        $session->persistState(['payment_data' => $paymentData]);

        $claimTwo = $manager->claim($session, 'chip');

        $manager->record($session, $claimTwo, new PaymentResult(
            status: PaymentStatus::Pending,
            paymentId: 'pay_gen2',
            redirectUrl: 'https://gateway.example.test/pay/gen2',
            amount: 1000,
            currency: 'MYR',
            provider: 'provider-two',
        ));

        $tokenTwo = data_get($session->payment_data, 'callback_token');

        expect($tokenOne)->toBeString()
            ->and($tokenTwo)->toBeString()
            ->and($tokenOne)->not->toBe($tokenTwo);

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(
            statusResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_gen2',
                amount: 1000,
                currency: 'MYR',
                provider: 'provider-two',
            ),
            tracker: $tracker,
        );

        $service = chk07Service($processor, (string) Str::uuid());
        app()->instance(CheckoutServiceInterface::class, $service);

        $controller = app(PaymentCallbackController::class);

        $rejected = $controller->success(Request::create(
            '/checkout/payment/chip/success',
            'GET',
            [
                'session' => $session->getKey(),
                'checkout_callback_token' => $tokenOne,
            ],
        ));

        $afterRejection = $session->fresh();

        expect($rejected->getTargetUrl())->toContain('/checkout/failed')
            ->and($afterRejection->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($afterRejection->payment_id)->toBe('pay_gen2')
            ->and(data_get($afterRejection->payment_data, 'callback_token'))->toBe($tokenTwo)
            ->and($tracker->statusCalls)->toBe(0)
            ->and($tracker->callbackCalls)->toBe(0);

        $accepted = $controller->success(Request::create(
            '/checkout/payment/chip/success',
            'GET',
            [
                'session' => $session->getKey(),
                'checkout_callback_token' => $tokenTwo,
            ],
        ));

        $afterAcceptance = $session->fresh();

        expect($accepted->getTargetUrl())->toContain((string) $session->getKey())
            ->and($afterAcceptance->status)->toBeInstanceOf(Completed::class)
            ->and($afterAcceptance->payment_id)->toBe('pay_gen2')
            ->and($afterAcceptance->payment_data['callback_token_consumed_at'] ?? null)->toBeString()
            ->and($tracker->statusCalls)->toBe(1)
            ->and($tracker->callbackCalls)->toBe(0)
            ->and($tracker->voided)->toBe([])
            ->and($tracker->refunded)->toBe([]);
    });
});

/**
 * Build generation 2 after a definitively failed generation 1, ending in the
 * pre-create-response window. The optional retained id models rows written
 * before the fix, where the previous generation's payment id still remained
 * on the session after the new claim.
 */
function chk07bSecondAttemptBeforeResponse(CheckoutSession $session, ?string $retainedPaymentId = null, string $gateway = 'chip'): void
{
    $manager = app(PaymentAttemptManager::class);
    $claimOne = $manager->claim($session, $gateway);

    $manager->record($session, $claimOne, new PaymentResult(
        status: PaymentStatus::Failed,
        paymentId: 'pay_old',
        amount: 1000,
        currency: 'MYR',
        provider: 'provider-one',
        message: 'Declined',
    ));

    $paymentData = $session->payment_data ?? [];
    $paymentData['provider'] = 'provider-two';
    $session->persistState(['payment_data' => $paymentData]);

    $manager->claim($session, $gateway);

    if ($retainedPaymentId !== null) {
        $session->persistState(['payment_id' => $retainedPaymentId]);
    }
}

describe('callback generation binding', function (): void {
    it('rejects a retained generation-1 success while attempt 2 is before its create response', function (): void {
        $session = chk07Session('chk07b-stale-success-pre-response');
        chk07bSecondAttemptBeforeResponse($session, retainedPaymentId: 'pay_old');

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public int $orders = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_old',
                amount: 1000,
                currency: 'MYR',
                provider: 'provider-one',
            ),
            tracker: $tracker,
        );

        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class, CheckoutFailed::class]);

        $service = chk07Service($processor, (string) Str::uuid(), $tracker);
        $pending = $session->fresh();

        expect($pending->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and((int) $pending->payment_attempts)->toBe(2)
            ->and($pending->payment_id)->toBe('pay_old')
            ->and(data_get($pending->payment_data, 'payment_attempt.number'))->toBe(2)
            ->and(data_get($pending->payment_data, 'payment_attempt_outcome.number'))->toBe(1);

        $result = $service->handlePaymentCallback($pending, 'success', [
            'status' => 'paid',
            'metadata' => [
                'checkout_attempt' => 1,
                'checkout_gateway' => 'chip',
            ],
        ]);

        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Payment belongs to a superseded payment attempt')
            ->and($fresh->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($fresh->payment_id)->toBe('pay_old')
            ->and($fresh->order_id)->toBeNull()
            ->and($fresh->error_message)->toBeNull()
            ->and(data_get($fresh->payment_data, 'verification_status'))->toBeNull()
            ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.number'))->toBe(1)
            ->and(data_get($fresh->payment_data, 'obsolete_attempt_receipts.0.attempt'))->toBe(1)
            ->and(data_get($fresh->payment_data, 'obsolete_attempt_receipts.0.payment_id'))->toBe('pay_old')
            ->and($tracker->orders)->toBe(0)
            ->and($tracker->voided)->toBe([])
            ->and($tracker->refunded)->toBe([]);

        Event::assertNotDispatched(CheckoutPaymentCompleted::class);
        Event::assertNotDispatched(CheckoutCompleted::class);
        Event::assertNotDispatched(CheckoutFailed::class);
    });

    it('rejects a generation-1 failure while attempt 2 is before its create response', function (): void {
        $session = chk07Session('chk07b-stale-failure-pre-response');
        chk07bSecondAttemptBeforeResponse($session, retainedPaymentId: 'pay_old');

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public int $orders = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(tracker: $tracker);

        Event::fake([CheckoutFailed::class, CheckoutCancelled::class]);

        $service = chk07Service($processor, (string) Str::uuid(), $tracker);
        $before = $session->fresh();

        $result = $service->handlePaymentCallback($before, 'failure', [
            'payment_id' => 'pay_old',
            'status' => 'failed',
            'metadata' => [
                'checkout_attempt' => 1,
                'checkout_gateway' => 'chip',
            ],
        ]);

        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Payment belongs to a superseded payment attempt')
            ->and($fresh->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($fresh->payment_id)->toBe('pay_old')
            ->and($fresh->error_message)->toBeNull()
            ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.number'))->toBe(1)
            ->and(data_get($fresh->step_states, 'process_payment'))->toBe($before->step_states['process_payment'])
            ->and($tracker->orders)->toBe(0)
            ->and($tracker->voided)->toBe([])
            ->and($tracker->refunded)->toBe([]);

        Event::assertNotDispatched(CheckoutFailed::class);
        Event::assertNotDispatched(CheckoutCancelled::class);
    });

    it('rejects a generation-1 cancel while attempt 2 is before its create response', function (): void {
        $session = chk07Session('chk07b-stale-cancel-pre-response');
        chk07bSecondAttemptBeforeResponse($session, retainedPaymentId: 'pay_old');

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public int $orders = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(tracker: $tracker);

        Event::fake([CheckoutCancelled::class, CheckoutFailed::class]);

        $service = chk07Service($processor, (string) Str::uuid(), $tracker);

        $result = $service->handlePaymentCallback($session->fresh(), 'cancel', [
            'payment_id' => 'pay_old',
            'metadata' => [
                'checkout_attempt' => 1,
                'checkout_gateway' => 'chip',
            ],
        ]);

        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Payment belongs to a superseded payment attempt')
            ->and($fresh->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($fresh->payment_id)->toBe('pay_old')
            ->and($tracker->orders)->toBe(0)
            ->and($tracker->voided)->toBe([])
            ->and($tracker->refunded)->toBe([]);

        Event::assertNotDispatched(CheckoutCancelled::class);
        Event::assertNotDispatched(CheckoutFailed::class);
    });

    it('accepts a current-generation success before its create response using attempt metadata', function (): void {
        $session = chk07Session('chk07b-current-success-pre-response');
        chk07bSecondAttemptBeforeResponse($session);

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public int $orders = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_new_2',
                amount: 1000,
                currency: 'MYR',
                provider: 'provider-two',
            ),
            tracker: $tracker,
        );

        $orderId = (string) Str::uuid();

        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class]);

        $service = chk07Service($processor, $orderId, $tracker);
        $pending = $session->fresh();

        expect($pending->payment_id)->toBeNull()
            ->and((int) $pending->payment_attempts)->toBe(2);

        $result = $service->handlePaymentCallback($pending, 'success', [
            'status' => 'paid',
            'metadata' => [
                'checkout_attempt' => 2,
                'checkout_gateway' => 'chip',
            ],
        ]);

        $fresh = $session->fresh();

        expect($result->success)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(Completed::class)
            ->and($fresh->payment_id)->toBe('pay_new_2')
            ->and($fresh->order_id)->toBe($orderId)
            ->and((int) $fresh->payment_attempts)->toBe(2)
            ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.number'))->toBe(2)
            ->and(data_get($fresh->payment_data, 'verification_status'))->toBe(PaymentStatus::Completed->value)
            ->and($tracker->orders)->toBe(1)
            ->and($tracker->voided)->toBe([])
            ->and($tracker->refunded)->toBe([]);

        Event::assertDispatchedTimes(CheckoutPaymentCompleted::class, 1);
        Event::assertDispatchedTimes(CheckoutCompleted::class, 1);
    });

    it('rejects a matching payment id from the wrong provider', function (): void {
        $session = chk07Session('chk07b-wrong-provider');
        chk07TwoGenerations($session);

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public int $orders = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_gen2',
                amount: 1000,
                currency: 'MYR',
                provider: 'provider-one',
            ),
            tracker: $tracker,
        );

        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class]);

        $service = chk07Service($processor, (string) Str::uuid(), $tracker);

        $result = $service->handlePaymentCallback($session->fresh(), 'success', [
            'status' => 'paid',
            'metadata' => [
                'checkout_attempt' => 2,
                'checkout_gateway' => 'chip',
            ],
        ]);

        $fresh = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Payment provider mismatch')
            ->and($fresh->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($fresh->payment_id)->toBe('pay_gen2')
            ->and($fresh->order_id)->toBeNull()
            ->and($fresh->error_message)->toBeNull()
            ->and(data_get($fresh->payment_data, 'verification_status'))->toBeNull()
            ->and($tracker->orders)->toBe(0)
            ->and($tracker->voided)->toBe([])
            ->and($tracker->refunded)->toBe([]);

        Event::assertNotDispatched(CheckoutPaymentCompleted::class);
        Event::assertNotDispatched(CheckoutCompleted::class);
    });

    it('fails closed when a pre-response callback has no generation evidence', function (): void {
        $session = chk07Session('chk07b-missing-generation');
        chk07bSecondAttemptBeforeResponse($session);

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public int $orders = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_unproven',
                amount: 1000,
                currency: 'MYR',
                provider: 'provider-two',
            ),
            tracker: $tracker,
        );

        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class]);

        $service = chk07Service($processor, (string) Str::uuid(), $tracker);
        $pending = $session->fresh();

        $missing = $service->handlePaymentCallback($pending, 'success', [
            'status' => 'paid',
        ]);

        $afterMissing = $session->fresh();

        expect($missing->success)->toBeFalse()
            ->and($missing->message)->toBe('Payment attempt generation is missing')
            ->and($afterMissing->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($afterMissing->payment_id)->toBeNull()
            ->and($afterMissing->order_id)->toBeNull()
            ->and($tracker->orders)->toBe(0);

        $superseded = $service->handlePaymentCallback($afterMissing, 'success', [
            'status' => 'paid',
            'metadata' => ['checkout_attempt' => 1],
        ]);

        $afterSuperseded = $session->fresh();

        expect($superseded->success)->toBeFalse()
            ->and($superseded->message)->toBe('Payment belongs to a superseded payment attempt')
            ->and($afterSuperseded->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($afterSuperseded->payment_id)->toBeNull()
            ->and($afterSuperseded->order_id)->toBeNull()
            ->and($tracker->orders)->toBe(0);

        Event::assertNotDispatched(CheckoutPaymentCompleted::class);
        Event::assertNotDispatched(CheckoutCompleted::class);
    });

    it('completes a current-generation CHIP notification carrying purchase metadata', function (): void {
        $session = chk07Session('chk07b-chip-notification-current');
        chk07bSecondAttemptBeforeResponse($session);

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public int $orders = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_notify_new',
                amount: 1000,
                currency: 'MYR',
                provider: 'provider-two',
            ),
            tracker: $tracker,
        );

        $orderId = (string) Str::uuid();

        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class]);

        $service = chk07Service($processor, $orderId, $tracker);
        $pending = $session->fresh();

        app()->instance(CheckoutServiceInterface::class, $service);
        app()->forgetInstance(HandleCheckoutPaymentCallback::class);
        app()->forgetInstance(ProcessCheckoutPaymentNotification::class);

        app(ProcessCheckoutPaymentNotification::class)->handle(
            payload: [
                'id' => 'pay_notify_new',
                'reference' => CheckoutPaymentReference::forSession($pending),
                'status' => 'paid',
                'purchase' => [
                    'total' => 1000,
                    'currency' => 'MYR',
                    'metadata' => [
                        'checkout_attempt' => 2,
                        'checkout_gateway' => 'chip',
                        'checkout_provider' => 'provider-two',
                    ],
                ],
            ],
            expectedGateways: ['chip'],
            callbackType: 'success',
        );

        $fresh = $session->fresh();

        expect($fresh->status)->toBeInstanceOf(Completed::class)
            ->and($fresh->payment_id)->toBe('pay_notify_new')
            ->and($fresh->order_id)->toBe($orderId)
            ->and($tracker->orders)->toBe(1)
            ->and($tracker->voided)->toBe([])
            ->and($tracker->refunded)->toBe([]);

        Event::assertDispatchedTimes(CheckoutPaymentCompleted::class, 1);
        Event::assertDispatchedTimes(CheckoutCompleted::class, 1);
    });

    it('retains an obsolete CHIP notification receipt without touching attempt 2', function (): void {
        $session = chk07Session('chk07b-chip-notification-obsolete');
        chk07bSecondAttemptBeforeResponse($session);

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public int $orders = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_notify_old',
                amount: 1000,
                currency: 'MYR',
                provider: 'provider-one',
            ),
            tracker: $tracker,
        );

        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class]);

        $service = chk07Service($processor, (string) Str::uuid(), $tracker);
        $pending = $session->fresh();

        app()->instance(CheckoutServiceInterface::class, $service);
        app()->forgetInstance(HandleCheckoutPaymentCallback::class);
        app()->forgetInstance(ProcessCheckoutPaymentNotification::class);

        app(ProcessCheckoutPaymentNotification::class)->handle(
            payload: [
                'id' => 'pay_notify_old',
                'reference' => CheckoutPaymentReference::forSession($pending),
                'status' => 'paid',
                'purchase' => [
                    'total' => 1000,
                    'currency' => 'MYR',
                    'metadata' => [
                        'checkout_attempt' => 1,
                        'checkout_gateway' => 'chip',
                        'checkout_provider' => 'provider-one',
                    ],
                ],
            ],
            expectedGateways: ['chip'],
            callbackType: 'success',
        );

        $fresh = $session->fresh();

        expect($fresh->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and($fresh->payment_id)->toBeNull()
            ->and($fresh->order_id)->toBeNull()
            ->and((int) $fresh->payment_attempts)->toBe(2)
            ->and($tracker->orders)->toBe(0)
            ->and(data_get($fresh->payment_data, 'obsolete_attempt_receipts.0.attempt'))->toBe(1)
            ->and(data_get($fresh->payment_data, 'obsolete_attempt_receipts.0.payment_id'))->toBe('pay_notify_old')
            ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.number'))->toBe(1);

        Event::assertNotDispatched(CheckoutPaymentCompleted::class);
        Event::assertNotDispatched(CheckoutCompleted::class);
    });

    it('clears the previous payment id when a new generation is claimed but keeps it as outcome evidence', function (): void {
        $session = chk07Session('chk07b-cleared-identity');
        chk07bSecondAttemptBeforeResponse($session);

        $fresh = $session->fresh();

        expect((int) $fresh->payment_attempts)->toBe(2)
            ->and($fresh->payment_id)->toBeNull()
            ->and($fresh->payment_redirect_url)->toBeNull()
            ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.number'))->toBe(1)
            ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.payment_id'))->toBe('pay_old');
    });

    it('completes a current-generation Cashier notification using nested object metadata', function (): void {
        $session = chk07Session('chk07b-cashier-notification-current', [
            'selected_payment_gateway' => 'cashier',
        ]);
        chk07bSecondAttemptBeforeResponse($session, gateway: 'cashier');

        $tracker = new class
        {
            public int $callbackCalls = 0;

            public int $statusCalls = 0;

            public int $orders = 0;

            public array $voided = [];

            public array $refunded = [];
        };

        $processor = chk07Processor(
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_stripe_new',
                amount: 1000,
                currency: 'MYR',
                provider: 'provider-two',
            ),
            tracker: $tracker,
        );

        $orderId = (string) Str::uuid();

        Event::fake([CheckoutPaymentCompleted::class, CheckoutCompleted::class]);

        $service = chk07Service($processor, $orderId, $tracker);
        $pending = $session->fresh();

        app()->instance(CheckoutServiceInterface::class, $service);
        app()->forgetInstance(HandleCheckoutPaymentCallback::class);
        app()->forgetInstance(ProcessCheckoutPaymentNotification::class);

        app(ProcessCheckoutPaymentNotification::class)->handle(
            payload: [
                'provider' => 'provider-two',
                'data' => [
                    'object' => [
                        'id' => 'pay_stripe_new',
                        'metadata' => [
                            'checkout_session_id' => CheckoutPaymentReference::forSession($pending),
                            'checkout_attempt' => 2,
                            'checkout_gateway' => 'cashier',
                            'checkout_provider' => 'provider-two',
                        ],
                    ],
                ],
            ],
            expectedGateways: ['cashier'],
            callbackType: 'success',
        );

        $fresh = $session->fresh();

        expect($fresh->status)->toBeInstanceOf(Completed::class)
            ->and($fresh->payment_id)->toBe('pay_stripe_new')
            ->and($fresh->order_id)->toBe($orderId)
            ->and($tracker->orders)->toBe(1)
            ->and($tracker->voided)->toBe([])
            ->and($tracker->refunded)->toBe([]);

        Event::assertDispatchedTimes(CheckoutPaymentCompleted::class, 1);
        Event::assertDispatchedTimes(CheckoutCompleted::class, 1);
    });

    it('carries attempt and gateway metadata into the CHIP purchase payload', function (): void {
        $session = chk07Session('chk07b-chip-metadata');

        $request = new PaymentRequest(
            amount: 1000,
            currency: 'MYR',
            gateway: 'chip',
            description: 'Metadata contract',
            metadata: [
                'checkout_attempt' => 2,
                'checkout_gateway' => 'chip',
                'checkout_provider' => 'provider-two',
            ],
        );

        $payload = (new ChipPurchasePayloadBuilder)->build($session, $request);

        expect(data_get($payload, 'purchase.metadata.checkout_attempt'))->toBe(2)
            ->and(data_get($payload, 'purchase.metadata.checkout_gateway'))->toBe('chip')
            ->and(data_get($payload, 'purchase.metadata.checkout_provider'))->toBe('provider-two');
    });
});
