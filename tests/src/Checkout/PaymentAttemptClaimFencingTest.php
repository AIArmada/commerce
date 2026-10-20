<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Checkout\Actions\CheckoutFinalizer;
use AIArmada\Checkout\Actions\HandleCheckoutPaymentCallback;
use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\PaymentCompensationInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Contracts\SessionDataTransformerInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentClaimStatus;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Events\CheckoutFailed;
use AIArmada\Checkout\Exceptions\InvalidCheckoutStateException;
use AIArmada\Checkout\Exceptions\PaymentException;
use AIArmada\Checkout\Integrations\Payment\CashierProcessor;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentAttemptManager;
use AIArmada\Checkout\Services\StepExecutor;
use AIArmada\Checkout\States\Cancelled;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed as PaymentFailedState;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Checkout\Support\CheckoutCallbackStatePolicy;
use AIArmada\Checkout\Support\ChipPurchasePayloadBuilder;
use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\ChipBillableUser;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\mock;

/*
|--------------------------------------------------------------------------
| CHK-01: claim and fence gateway attempts
|--------------------------------------------------------------------------
|
| One logical checkout must never create a second active charge, reopen a
| terminal session from a stale instance, or let a late gateway result
| overwrite a winning payment. These regressions count gateway calls and
| assert exactly one order/fulfillment outcome.
|
*/

beforeEach(function (): void {
    Model::clearBootedModels();
});

function chk01TrackStep(string $identifier, array $dependencies = [], ?object $tracker = null): CheckoutStepInterface
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

function chk01Session(string $cartId, int $total = 1000, array $overrides = []): CheckoutSession
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
function chk01Processor(
    Closure $onCreate,
    ?object $voidTracker = null,
    ?PaymentResult $callbackResult = null,
    string $identifier = 'chip',
): PaymentProcessorInterface {
    return new class($onCreate, $voidTracker, $callbackResult, $identifier) implements PaymentCompensationInterface, PaymentProcessorInterface
    {
        public function __construct(
            private readonly Closure $onCreate,
            private readonly ?object $voidTracker,
            private readonly ?PaymentResult $callbackResult,
            private readonly string $identifier,
        ) {}

        public function getIdentifier(): string
        {
            return $this->identifier;
        }

        public function getName(): string
        {
            return 'checkout-test-gateway';
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

function chk01CheckoutService(PaymentProcessorInterface $processor, object $tracker): CheckoutService
{
    $resolver = mock(PaymentGatewayResolverInterface::class);
    $resolver->shouldReceive('hasGateway')->andReturnTrue();
    $resolver->shouldReceive('resolve')->andReturn($processor);
    $resolver->shouldReceive('getDefaultGateway')->andReturn('chip');

    $registry = new CheckoutStepRegistry;
    $registry->register('calculate_shipping', chk01TrackStep('calculate_shipping', [], $tracker));
    $registry->register('process_payment', new ProcessPaymentStep($resolver));
    $registry->register('create_order', chk01TrackStep('create_order', ['process_payment'], $tracker));
    $registry->setOrder(['calculate_shipping', 'process_payment', 'create_order']);

    return new CheckoutService(
        stepRegistry: $registry,
        events: app(Dispatcher::class),
        stepExecutor: new StepExecutor($registry, app(Dispatcher::class)),
        finalizer: new CheckoutFinalizer(app(Dispatcher::class)),
        paymentResolver: $resolver,
    );
}

class Chk01CountingBillingTransformer implements SessionDataTransformerInterface
{
    public static int $runs = 0;

    public function transform(array $data, CheckoutSession $session): array
    {
        self::$runs++;
        $data['transformed_runs'] = self::$runs;

        return $data;
    }
}

describe('claim and fence gateway attempts', function (): void {
    it('fences a sequential stale replay after completion', function (): void {
        $tracker = new class
        {
            public int $creates = 0;

            public array $executed = [];
        };

        $processor = chk01Processor(function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
            $tracker->creates++;

            return PaymentResult::success('pay_' . $tracker->creates, amount: 1000);
        });

        $service = chk01CheckoutService($processor, $tracker);

        $session = chk01Session('stale-replay-cart');
        $stale = $session->fresh();

        $first = $service->processCheckout($session);
        expect($first->success)->toBeTrue();

        $second = $service->processCheckout($stale);

        expect($second->success)->toBeTrue()
            ->and($second->status)->toBeInstanceOf(Completed::class)
            ->and($tracker->creates)->toBe(1)
            ->and($stale->fresh()->status)->toBeInstanceOf(Completed::class)
            ->and($stale->fresh()->payment_id)->toBe('pay_1')
            ->and($tracker->executed)->toBe(['calculate_shipping', 'create_order']);
    });

    it('replays an existing redirect for a stale duplicate instead of completing the order', function (): void {
        $tracker = new class
        {
            public int $creates = 0;

            public array $executed = [];
        };

        $processor = chk01Processor(function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
            $tracker->creates++;

            return PaymentResult::pending('pay_redirect_1', 'https://gateway.example.test/pay/1');
        });

        $service = chk01CheckoutService($processor, $tracker);

        $session = chk01Session('duplicate-redirect-cart');
        $stale = $session->fresh();

        $first = $service->processCheckout($session);
        expect($first->requiresRedirect())->toBeTrue()
            ->and($first->redirectUrl)->toBe('https://gateway.example.test/pay/1');

        $second = $service->processCheckout($stale);

        expect($second->requiresRedirect())->toBeTrue()
            ->and($second->redirectUrl)->toBe('https://gateway.example.test/pay/1')
            ->and($tracker->creates)->toBe(1)
            ->and($tracker->executed)->toBe(['calculate_shipping'])
            ->and($stale->fresh()->payment_attempts)->toBe(1);
    });

    it('lets a callback complete first and voids only the superseded late payment', function (): void {
        $tracker = new class
        {
            public int $creates = 0;

            public array $executed = [];

            public array $voided = [];
        };

        $holder = new class
        {
            public ?CheckoutService $service = null;
        };

        $processor = chk01Processor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request) use ($tracker, $holder): PaymentResult {
                $tracker->creates++;

                (new HandleCheckoutPaymentCallback($holder->service, new CheckoutCallbackStatePolicy))
                    ->handle((string) $session->getKey(), 'success', [
                        'status' => 'paid',
                        'metadata' => ['checkout_attempt' => 1],
                    ]);

                return PaymentResult::success('pay_late_1', amount: 1000);
            },
            voidTracker: $tracker,
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_callback_1',
                amount: 1000,
                currency: 'MYR',
            ),
        );

        $service = chk01CheckoutService($processor, $tracker);
        $holder->service = $service;

        $session = chk01Session('callback-before-create-cart');

        $result = $service->processCheckout($session);

        $fresh = $session->fresh();

        expect($result->success)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(Completed::class)
            ->and($fresh->payment_id)->toBe('pay_callback_1')
            ->and($tracker->creates)->toBe(1)
            ->and($tracker->voided)->toBe(['pay_late_1'])
            ->and($tracker->executed)->toBe(['calculate_shipping', 'create_order'])
            ->and($fresh->payment_attempts)->toBe(1);
    });

    it('ignores a late failure after a concurrent callback completion', function (): void {
        Event::fake([CheckoutFailed::class]);

        $tracker = new class
        {
            public int $creates = 0;

            public array $executed = [];

            public array $voided = [];
        };

        $holder = new class
        {
            public ?CheckoutService $service = null;
        };

        $processor = chk01Processor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request) use ($tracker, $holder): PaymentResult {
                $tracker->creates++;

                (new HandleCheckoutPaymentCallback($holder->service, new CheckoutCallbackStatePolicy))
                    ->handle((string) $session->getKey(), 'success', [
                        'status' => 'paid',
                        'metadata' => ['checkout_attempt' => 1],
                    ]);

                return PaymentResult::failed('Declined after completion');
            },
            voidTracker: $tracker,
            callbackResult: new PaymentResult(
                status: PaymentStatus::Completed,
                paymentId: 'pay_callback_2',
                amount: 1000,
                currency: 'MYR',
            ),
        );

        $service = chk01CheckoutService($processor, $tracker);
        $holder->service = $service;

        $session = chk01Session('late-failure-cart');

        $result = $service->processCheckout($session);

        $fresh = $session->fresh();

        expect($result->success)->toBeTrue()
            ->and($fresh->status)->toBeInstanceOf(Completed::class)
            ->and($fresh->error_message)->toBeNull()
            ->and($fresh->payment_id)->toBe('pay_callback_2')
            ->and($tracker->creates)->toBe(1)
            ->and($tracker->voided)->toBe([])
            ->and($tracker->executed)->toBe(['calculate_shipping', 'create_order']);

        Event::assertNotDispatched(CheckoutFailed::class);
    });

    it('rejects a stale replay whose session was cancelled without compensating or reopening', function (): void {
        $tracker = new class
        {
            public int $creates = 0;

            public array $executed = [];

            public array $voided = [];
        };

        $processor = chk01Processor(
            onCreate: function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
                $tracker->creates++;

                return PaymentResult::success('pay_unexpected', amount: 1000, currency: 'MYR');
            },
            voidTracker: $tracker,
        );

        $service = chk01CheckoutService($processor, $tracker);

        $session = chk01Session('cancelled-replay-cart');
        $stale = $session->fresh();

        $service->cancelCheckout($session);

        expect(fn (): mixed => $service->processCheckout($stale))
            ->toThrow(InvalidCheckoutStateException::class);

        expect($tracker->creates)->toBe(0)
            ->and($tracker->voided)->toBe([])
            ->and($stale->fresh()->status)->toBeInstanceOf(Cancelled::class)
            ->and($stale->fresh()->payment_attempts)->toBe(0);
    });

    it('rejects the gateway claim when the session is cancelled before the external call', function (): void {
        $tracker = new class
        {
            public int $creates = 0;

            public array $executed = [];

            public array $voided = [];
        };

        $holder = new class
        {
            public ?CheckoutService $service = null;
        };

        $processor = new class($tracker, $holder) implements PaymentCompensationInterface, PaymentProcessorInterface
        {
            public function __construct(
                private readonly object $tracker,
                private readonly object $holder,
            ) {}

            public function getIdentifier(): string
            {
                return 'chip';
            }

            public function getName(): string
            {
                return 'chip';
            }

            public function isAvailable(CheckoutSession $session): bool
            {
                $fresh = CheckoutSession::find($session->getKey());
                $this->holder->service->cancelCheckout($fresh);

                return true;
            }

            public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
            {
                $this->tracker->creates++;

                return PaymentResult::failed('should not be called');
            }

            public function handleCallback(array $payload): PaymentResult
            {
                return PaymentResult::failed('unused');
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

                return new PaymentResult(status: PaymentStatus::Cancelled, paymentId: $paymentId);
            }
        };

        $service = chk01CheckoutService($processor, $tracker);
        $holder->service = $service;

        $session = chk01Session('cancel-before-create-cart');

        $result = $service->processCheckout($session);

        expect($result->success)->toBeFalse()
            ->and($result->errors)->toHaveKey('interrupted')
            ->and($tracker->creates)->toBe(0)
            ->and($tracker->voided)->toBe([])
            ->and($session->fresh()->status)->toBeInstanceOf(Cancelled::class);
    });

    it('refuses a retry while the first attempt is still in flight', function (): void {
        $tracker = new class
        {
            public int $creates = 0;

            public array $executed = [];

            public array $voided = [];
        };

        $captured = new class
        {
            public ?Throwable $exception = null;
        };

        $holder = new class
        {
            public ?CheckoutService $service = null;
        };

        $processor = chk01Processor(function (CheckoutSession $session, PaymentRequest $request) use ($tracker, $captured, $holder): PaymentResult {
            $tracker->creates++;

            try {
                $holder->service?->retryPayment(CheckoutSession::find($session->getKey()));
            } catch (Throwable $e) {
                $captured->exception = $e;
            }

            return PaymentResult::pending('pay_inflight_1', 'https://gateway.example.test/pay/inflight');
        });

        $service = chk01CheckoutService($processor, $tracker);
        $holder->service = $service;

        $session = chk01Session('process-vs-retry-cart');

        $result = $service->processCheckout($session);

        expect($captured->exception)->toBeInstanceOf(InvalidCheckoutStateException::class)
            ->and($result->requiresRedirect())->toBeTrue()
            ->and($tracker->creates)->toBe(1)
            ->and($session->fresh()->payment_attempts)->toBe(1);
    });

    it('reconciles an unknown outcome by retrying the frozen attempt identity', function (): void {
        config()->set('checkout.payment.retry_limit', 3);

        $tracker = new class
        {
            public int $creates = 0;

            public array $idempotencyKeys = [];

            public array $attempts = [];

            public array $executed = [];

            public array $voided = [];
        };

        $processor = chk01Processor(function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
            $tracker->creates++;
            $tracker->idempotencyKeys[] = $request->metadata['checkout_idempotency_key'] ?? null;
            $tracker->attempts[] = $request->metadata['checkout_attempt'] ?? null;

            if ($tracker->creates === 1) {
                throw new RuntimeException('Gateway timeout');
            }

            return PaymentResult::pending('pay_recovered_1', 'https://gateway.example.test/pay/recovered');
        });

        $service = chk01CheckoutService($processor, $tracker);

        $session = chk01Session('unknown-outcome-retry-cart');

        expect(fn (): mixed => $service->processCheckout($session))
            ->toThrow(RuntimeException::class, 'Gateway timeout');

        $failed = $session->fresh();

        expect($failed->status)->toBeInstanceOf(PaymentFailedState::class)
            ->and($failed->payment_attempts)->toBe(1);

        // An explicit retry scenario keeps the pre-payment steps valid.
        $failed->setStepState('calculate_shipping', StepStatus::Completed);

        $result = $service->retryPayment($failed);

        expect($result->requiresRedirect())->toBeTrue()
            ->and($tracker->creates)->toBe(2)
            ->and($tracker->idempotencyKeys[0])->toBe($session->getKey() . ':attempt:1')
            ->and($tracker->idempotencyKeys[1])->toBe($tracker->idempotencyKeys[0])
            ->and($tracker->attempts)->toBe([1, 1])
            ->and($session->fresh()->payment_attempts)->toBe(1);
    });

    it('counts each claim once and enforces the retry limit', function (): void {
        config()->set('checkout.payment.retry_limit', 2);

        $tracker = new class
        {
            public int $creates = 0;

            public array $executed = [];

            public array $voided = [];
        };

        $processor = chk01Processor(function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
            $tracker->creates++;

            return PaymentResult::failed('Declined');
        });

        $service = chk01CheckoutService($processor, $tracker);

        $session = chk01Session('retry-limit-cart');

        $service->processCheckout($session);

        expect($session->fresh()->status)->toBeInstanceOf(PaymentFailedState::class)
            ->and($session->fresh()->payment_attempts)->toBe(1);

        $firstFailure = $session->fresh();
        $firstFailure->setStepState('calculate_shipping', StepStatus::Completed);

        $service->retryPayment($firstFailure);

        expect($session->fresh()->status)->toBeInstanceOf(PaymentFailedState::class)
            ->and($session->fresh()->payment_attempts)->toBe(2);

        expect(fn (): mixed => $service->retryPayment($session->fresh()))
            ->toThrow(PaymentException::class);

        expect($tracker->creates)->toBe(2)
            ->and($session->fresh()->payment_attempts)->toBe(2);
    });

    it('completes a free checkout without claiming an attempt', function (): void {
        $tracker = new class
        {
            public int $creates = 0;

            public array $executed = [];

            public array $voided = [];
        };

        $processor = chk01Processor(function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
            $tracker->creates++;

            return PaymentResult::success('pay_free_1', amount: 0);
        });

        $service = chk01CheckoutService($processor, $tracker);

        $session = chk01Session('free-order-cart', total: 0);

        $result = $service->processCheckout($session);

        expect($result->success)->toBeTrue()
            ->and($session->fresh()->status)->toBeInstanceOf(Completed::class)
            ->and($session->fresh()->payment_attempts)->toBe(0)
            ->and($tracker->creates)->toBe(0)
            ->and(data_get($session->fresh()->payment_data, 'type'))->toBe('free_order');
    });

    it('does not transform or write state during a stale replay after completion', function (): void {
        config()->set('checkout.transformers.billing', Chk01CountingBillingTransformer::class);
        Chk01CountingBillingTransformer::$runs = 0;

        $tracker = new class
        {
            public int $creates = 0;

            public array $executed = [];

            public array $voided = [];
        };

        $processor = chk01Processor(function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
            $tracker->creates++;

            return PaymentResult::success('pay_transformer_1', amount: 1000);
        });

        $service = chk01CheckoutService($processor, $tracker);

        $session = chk01Session('transformer-replay-cart');
        $stale = $session->fresh();

        $service->processCheckout($session);

        expect(Chk01CountingBillingTransformer::$runs)->toBe(1);

        $service->processCheckout($stale);

        expect(Chk01CountingBillingTransformer::$runs)->toBe(1)
            ->and(data_get($stale->fresh()->billing_data, 'transformed_runs'))->toBe(1);
    });
});

/*
|--------------------------------------------------------------------------
| CHK-10: the complete provider request is frozen into the claim
|--------------------------------------------------------------------------
|
| Customer, billing, callback, metadata, and billable inputs must be
| captured exactly once when an attempt is claimed. Adoption replays that
| snapshot, including frozen nulls, and refuses an incomplete identity rather
| than rebuilding it from mutable session state under the same idempotency
| key.
|
*/

describe('frozen payment request snapshot', function (): void {
    it('freezes the complete provider request at claim time and replays it unchanged after adoption', function (): void {
        config()->set('checkout.payment.retry_limit', 3);

        $tracker = new class
        {
            public int $creates = 0;

            /** @var list<PaymentRequest> */
            public array $requests = [];

            public array $executed = [];

            public array $voided = [];
        };

        $processor = chk01Processor(function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
            $tracker->creates++;
            $tracker->requests[] = $request;

            if ($tracker->creates === 1) {
                return PaymentResult::uncertain('Gateway timed out');
            }

            return PaymentResult::pending('pay_frozen_request', 'https://gateway.example.test/frozen');
        });

        $service = chk01CheckoutService($processor, $tracker);

        $session = chk01Session('frozen-request-cart', 1000, [
            'customer_id' => '11111111-1111-1111-1111-111111111111',
            'billing_data' => [
                'name' => 'Frozen Customer',
                'email' => 'frozen@example.com',
                'phone' => '+60123456789',
                'line1' => '1 Frozen Street',
                'line2' => 'Suite 2',
                'city' => 'Frozen City',
                'state' => 'Selangor',
                'postcode' => '40000',
                'country_code' => 'MY',
            ],
            'payment_data' => [
                'provider' => 'provider-frozen',
                'payment_method' => 'card_frozen',
            ],
        ]);

        $service->processCheckout($session);
        $claimed = $session->fresh();

        $firstRequest = $tracker->requests[0];
        $firstSnapshot = $firstRequest->toSnapshot();

        expect($tracker->creates)->toBe(1)
            ->and($claimed->status)->toBeInstanceOf(PaymentProcessing::class)
            ->and((int) $claimed->payment_attempts)->toBe(1)
            ->and(data_get($claimed->payment_data, 'payment_attempt.request'))->toBe($firstSnapshot);

        $paymentData = $claimed->payment_data ?? [];
        $paymentData['callback_token'] = 'mutated-callback-token-after-claim';
        $paymentData['payment_method'] = 'card_mutated';

        $claimed->persistState([
            'cart_id' => 'mutated-cart-id',
            'customer_id' => '22222222-2222-2222-2222-222222222222',
            'selected_payment_gateway' => 'cashier',
            'billing_data' => [
                'name' => 'Mutated Customer',
                'email' => 'mutated@example.com',
                'phone' => '+60199999999',
                'line1' => '9 Mutated Street',
                'city' => 'Mutated City',
                'country_code' => 'SG',
            ],
            'payment_data' => $paymentData,
        ]);

        $claimed->setStepState('calculate_shipping', StepStatus::Completed);

        $recovered = $service->retryPayment($claimed);
        $fresh = $session->fresh();

        expect($recovered->requiresRedirect())->toBeTrue()
            ->and($tracker->creates)->toBe(2)
            ->and($tracker->requests[1]->toSnapshot())->toBe($firstSnapshot)
            ->and($tracker->requests[1]->paymentMethod)->toBe('card_frozen')
            ->and($tracker->requests[1]->metadata['cart_id'])->toBe('frozen-request-cart')
            ->and($tracker->requests[1]->metadata['customer_id'])->toBe('11111111-1111-1111-1111-111111111111')
            ->and((int) $fresh->payment_attempts)->toBe(1)
            ->and(data_get($fresh->payment_data, 'payment_attempt.request'))->toBe($firstSnapshot)
            ->and(data_get($fresh->payment_data, 'callback_token'))->toBe('mutated-callback-token-after-claim')
            ->and($fresh->selected_payment_gateway)->toBe('cashier');
    });

    it('requires a complete frozen identity when adopting and preserves frozen nulls', function (): void {
        $manager = app(PaymentAttemptManager::class);

        $requestSnapshot = [
            'amount' => 1000,
            'currency' => 'MYR',
            'gateway' => 'chip',
            'description' => 'Frozen order',
            'customer_email' => null,
            'customer_name' => null,
            'customer_phone' => null,
            'success_url' => 'https://example.test/success',
            'failure_url' => 'https://example.test/failure',
            'cancel_url' => 'https://example.test/cancel',
            'metadata' => ['checkout_attempt' => 1],
            'payment_method' => null,
            'provider' => null,
            'idempotency_key' => 'frozen-identity-cart:attempt:1',
            'billable_type' => null,
            'billable_id' => null,
            'client' => [
                'email' => null,
                'full_name' => null,
                'phone' => null,
                'street_address' => null,
                'city' => null,
                'state' => null,
                'zip_code' => null,
                'country' => null,
            ],
            'client_frozen' => true,
            'billable_frozen' => true,
        ];

        $completeClaim = [
            'number' => 1,
            'gateway' => 'chip',
            'provider' => null,
            'amount' => 1000,
            'currency' => 'MYR',
            'reference' => 'chk_00000000-0000-0000-0000-000000000001',
            'idempotency_key' => 'frozen-identity-cart:attempt:1',
            'payment_method' => null,
            'claimed_at' => now()->toIso8601String(),
            'request' => $requestSnapshot,
        ];

        $makeSession = function (array $claim): CheckoutSession {
            $session = chk01Session('frozen-identity-cart', 1000, [
                'payment_attempts' => 1,
                'payment_data' => [
                    'retry_requested_for_attempt' => 1,
                    'callback_token' => 'chk10-callback-token',
                    'callback_token_created_at' => now()->toIso8601String(),
                    'payment_method' => 'pm_mutable_after_claim',
                    'payment_attempt' => $claim,
                ],
            ]);

            $session->transitionStatus(Processing::class);

            return $session->fresh();
        };

        $missingRequest = $completeClaim;
        unset($missingRequest['request']);

        $missingPaymentMethod = $completeClaim;
        unset($missingPaymentMethod['payment_method']);

        expect(fn (): mixed => $manager->claim($makeSession($missingRequest), 'chip'))
            ->toThrow(PaymentException::class);

        expect(fn (): mixed => $manager->claim($makeSession($missingPaymentMethod), 'chip'))
            ->toThrow(PaymentException::class);

        $adopted = $manager->claim($makeSession($completeClaim), 'chip');

        expect($adopted->status)->toBe(PaymentClaimStatus::Adopted)
            ->and($adopted->provider)->toBeNull()
            ->and($adopted->paymentMethod)->toBeNull()
            ->and($adopted->request)->toBe($requestSnapshot);
    });

    it('applies changed inputs to a genuine replacement generation with a new key', function (): void {
        config()->set('checkout.payment.retry_limit', 3);

        $tracker = new class
        {
            public int $creates = 0;

            /** @var list<PaymentRequest> */
            public array $requests = [];

            public array $executed = [];

            public array $voided = [];
        };

        $processor = chk01Processor(function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
            $tracker->creates++;
            $tracker->requests[] = $request;

            return $tracker->creates === 1
                ? PaymentResult::failed('Declined')
                : PaymentResult::pending('pay_new_generation', 'https://gateway.example.test/new-generation');
        });

        $service = chk01CheckoutService($processor, $tracker);

        $session = chk01Session('new-generation-inputs', 1000, [
            'billing_data' => [
                'name' => 'Old Customer',
                'email' => 'old@example.com',
                'line1' => '1 Old Street',
                'city' => 'Old City',
                'country_code' => 'MY',
            ],
            'payment_data' => ['payment_method' => null],
        ]);

        $first = $service->processCheckout($session);

        expect($first->success)->toBeFalse()
            ->and($session->fresh()->payment_attempts)->toBe(1)
            ->and($tracker->requests[0]->paymentMethod)->toBeNull();

        $claimed = $session->fresh();
        $paymentData = $claimed->payment_data ?? [];
        $paymentData['payment_method'] = 'card_new';

        $claimed->persistState([
            'billing_data' => [
                'name' => 'New Customer',
                'email' => 'new@example.com',
                'line1' => '9 New Street',
                'city' => 'New City',
                'country_code' => 'SG',
            ],
            'payment_data' => $paymentData,
        ]);
        $claimed->setStepState('calculate_shipping', StepStatus::Completed);

        $retry = $service->retryPayment($claimed);

        expect($retry->requiresRedirect())->toBeTrue()
            ->and($session->fresh()->payment_attempts)->toBe(2)
            ->and($tracker->requests[1]->paymentMethod)->toBe('card_new')
            ->and($tracker->requests[1]->customerEmail)->toBe('new@example.com')
            ->and($tracker->requests[1]->client['street_address'])->toBe('9 New Street')
            ->and($tracker->requests[1]->idempotencyKey)->toEndWith(':attempt:2')
            ->and($tracker->requests[1]->idempotencyKey)->not->toBe($tracker->requests[0]->idempotencyKey);
    });

    it('builds the CHIP client from the frozen snapshot and ignores mutable billing', function (): void {
        $session = new CheckoutSession;
        $session->setAttribute('id', 'session-frozen-client');
        $session->setAttribute('billing_data', [
            'name' => 'Mutated Customer',
            'email' => 'mutated@example.com',
            'line1' => '9 Mutated Street',
            'city' => 'Mutated City',
            'state' => 'Johor',
            'postcode' => '80000',
            'country_code' => 'SG',
        ]);

        $frozenRequest = PaymentRequest::fromSnapshot([
            'amount' => 1000,
            'currency' => 'MYR',
            'gateway' => 'chip',
            'description' => 'Frozen order',
            'customer_email' => 'frozen@example.com',
            'customer_name' => 'Frozen Customer',
            'customer_phone' => null,
            'success_url' => null,
            'failure_url' => null,
            'cancel_url' => null,
            'metadata' => [],
            'payment_method' => null,
            'provider' => null,
            'idempotency_key' => 'session-frozen-client:attempt:1',
            'billable_type' => null,
            'billable_id' => null,
            'client' => [
                'email' => 'frozen@example.com',
                'full_name' => 'Frozen Customer',
                'phone' => null,
                'street_address' => '1 Frozen Street',
                'city' => 'Frozen City',
                'state' => null,
                'zip_code' => null,
                'country' => 'MY',
            ],
            'client_frozen' => true,
            'billable_frozen' => true,
        ]);

        $builder = new ChipPurchasePayloadBuilder;

        expect($builder->build($session, $frozenRequest)['client'])->toBe([
            'email' => 'frozen@example.com',
            'full_name' => 'Frozen Customer',
            'street_address' => '1 Frozen Street',
            'city' => 'Frozen City',
            'country' => 'MY',
        ]);

        $directRequest = new PaymentRequest(
            amount: 1000,
            currency: 'MYR',
            gateway: 'chip',
            customerEmail: 'request@example.com',
            customerName: 'Request Customer',
            clientFrozen: false,
        );

        expect($builder->build($session, $directRequest)['client'])->toBe([
            'email' => 'request@example.com',
            'full_name' => 'Request Customer',
            'street_address' => '9 Mutated Street',
            'city' => 'Mutated City',
            'state' => 'Johor',
            'zip_code' => '80000',
            'country' => 'SG',
        ]);
    });

    it('charges the frozen billable on replay instead of the mutable session billable', function (): void {
        $frozen = ChipBillableUser::query()->create([
            'name' => 'Frozen Billable',
            'email' => 'frozen-billable@example.com',
            'password' => bcrypt('secret'),
        ]);

        $mutable = ChipBillableUser::query()->create([
            'name' => 'Mutated Billable',
            'email' => 'mutated-billable@example.com',
            'password' => bcrypt('secret'),
        ]);

        $payment = mock(PaymentContract::class);
        $payment->shouldReceive('toArray')->andReturn([
            'id' => 'pay_frozen_billable',
            'status' => 'succeeded',
            'currency' => 'MYR',
            'raw_amount' => 2500,
        ]);
        $payment->shouldReceive('id')->andReturn('pay_frozen_billable');
        $payment->shouldReceive('redirectUrl')->andReturn(null);
        $payment->shouldReceive('status')->andReturn('succeeded');
        $payment->shouldReceive('rawAmount')->andReturn(2500);
        $payment->shouldReceive('currency')->andReturn('MYR');
        $payment->shouldReceive('isSucceeded')->andReturn(true);

        $gateway = mock(GatewayContract::class);
        $gateway->shouldReceive('name')->andReturn('chip');
        $gateway->shouldReceive('charge')
            ->once()
            ->with(
                Mockery::on(fn (mixed $candidate): bool => $candidate instanceof ChipBillableUser
                    && (string) $candidate->getKey() === (string) $frozen->getKey()),
                2500,
                null,
                Mockery::type('array'),
            )
            ->andReturn($payment);

        $gatewayManager = mock(GatewayManager::class);
        $gatewayManager->shouldReceive('gateway')->once()->with('chip')->andReturn($gateway);
        app()->instance(GatewayManager::class, $gatewayManager);

        $session = new CheckoutSession;
        $session->setAttribute('id', 'session-frozen-billable');
        $session->setRelation('billable', $mutable);
        $session->setRelation('customer', null);

        $request = new PaymentRequest(
            amount: 2500,
            currency: 'MYR',
            gateway: 'cashier',
            description: 'Frozen billable order',
            successUrl: 'https://example.test/success',
            failureUrl: 'https://example.test/failure',
            cancelUrl: 'https://example.test/cancel',
            provider: 'chip',
            billableType: ChipBillableUser::class,
            billableId: (string) $frozen->getKey(),
            clientFrozen: true,
        );

        $result = app(CashierProcessor::class)->createPayment($session, $request);

        expect($result->status)->toBe(PaymentStatus::Completed);

        $unresolvable = new PaymentRequest(
            amount: 2500,
            currency: 'MYR',
            gateway: 'cashier',
            billableType: ChipBillableUser::class,
            billableId: '00000000-0000-0000-0000-000000000000',
            clientFrozen: true,
        );

        $fallbackSession = new CheckoutSession;
        $fallbackSession->setAttribute('id', 'session-unresolvable-billable');
        $fallbackSession->setRelation('billable', mock(BillableContract::class));

        $failed = app(CashierProcessor::class)->createPayment($fallbackSession, $unresolvable);

        expect($failed->status)->toBe(PaymentStatus::Failed)
            ->and($failed->message)->toContain('billable');
    });
});
