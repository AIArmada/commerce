<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Checkout\Actions\CheckoutFinalizer;
use AIArmada\Checkout\Actions\HandleCheckoutPaymentCallback;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\PaymentCompensationInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Checkout\Events\CheckoutFailed;
use AIArmada\Checkout\Exceptions\InvalidCheckoutStateException;
use AIArmada\Checkout\Http\Controllers\PaymentCallbackController;
use AIArmada\Checkout\Integrations\Payment\CashierChipProcessor;
use AIArmada\Checkout\Integrations\Payment\CashierProcessor;
use AIArmada\Checkout\Integrations\Payment\ChipProcessor;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use AIArmada\Checkout\Services\StepExecutor;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Cancelled;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed as PaymentFailedState;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\CalculatePricingStep;
use AIArmada\Checkout\Steps\CreateOrderStep;
use AIArmada\Checkout\Steps\PersistCustomerStep;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Checkout\Support\CheckoutCallbackStatePolicy;
use AIArmada\Checkout\Support\ChipPurchasePayloadBuilder;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Orders\Contracts\OrderServiceInterface;
use AIArmada\Orders\Models\Order;
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Pricing\Data\PriceResultData;
use AIArmada\Products\Models\Product;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use function Pest\Laravel\mock;

beforeEach(function (): void {
    // Owner-scope config snapshots at model boot and the boot registry is
    // static per worker process, so clear it for deterministic per-test
    // scoping regardless of which tests ran earlier in this process.
    Model::clearBootedModels();
});

function regressionTrackStep(string $identifier, array $dependencies = [], ?object $tracker = null, ?Closure $onHandle = null): CheckoutStepInterface
{
    return new class($identifier, $dependencies, $tracker, $onHandle) implements CheckoutStepInterface
    {
        public function __construct(
            private readonly string $identifier,
            private readonly array $dependencies,
            private readonly ?object $tracker,
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
            if ($this->tracker !== null) {
                $this->tracker->executed[] = $this->identifier;
            }

            if ($this->onHandle !== null) {
                return ($this->onHandle)($session, $this->identifier);
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

function regressionCheckoutService(CheckoutStepRegistry $registry, ?PaymentGatewayResolver $paymentResolver = null): CheckoutService
{
    return new CheckoutService(
        stepRegistry: $registry,
        events: app(Dispatcher::class),
        stepExecutor: new StepExecutor($registry, app(Dispatcher::class)),
        finalizer: new CheckoutFinalizer(app(Dispatcher::class)),
        paymentResolver: $paymentResolver,
    );
}

function regressionStubProcessor(PaymentResult $callbackResult, ?object $voidTracker = null): PaymentProcessorInterface
{
    return new class($callbackResult, $voidTracker) implements PaymentCompensationInterface, PaymentProcessorInterface
    {
        public function __construct(
            private readonly PaymentResult $callbackResult,
            private readonly ?object $voidTracker,
        ) {}

        public function getIdentifier(): string
        {
            return 'repair-stub';
        }

        public function getName(): string
        {
            return 'repair-stub';
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
            return $this->callbackResult;
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

describe('cashier payment id', function (): void {
    it('prefers the Stripe payment object id over the top-level event id', function (): void {
        $result = app(CashierProcessor::class)->handleCallback([
            'id' => 'evt_test_123',
            'status' => 'completed',
            'amount' => 1000,
            'currency' => 'MYR',
            'data' => ['object' => ['id' => 'pi_test_456']],
        ]);

        expect($result->paymentId)->toBe('pi_test_456');
    });

    it('falls back to the top-level id for non-Stripe payloads', function (): void {
        $result = app(CashierProcessor::class)->handleCallback([
            'id' => 'pay_standalone_1',
            'status' => 'completed',
            'amount' => 500,
            'currency' => 'MYR',
        ]);

        expect($result->paymentId)->toBe('pay_standalone_1');
    });
});

describe('startCheckout customer validation', function (): void {
    it('rejects a checkout customer owned by a different owner', function (): void {
        config()->set('checkout.owner.enabled', true);
        config()->set('checkout.owner.auto_assign_on_create', true);

        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $foreignCustomer = OwnerContext::withOwner($ownerB, function (): Customer {
            $customer = Customer::create([
                'first_name' => 'Foreign',
                'last_name' => 'Customer',
                'email' => 'foreign-scope@example.com',
            ]);
            $customer->forceFill(['is_guest' => true])->save();

            return $customer;
        });

        Cart::setIdentifier('checkout-validation-cart');
        Cart::add('checkout-validation-sku', 'Checkout Item', 1500, 1);
        $cartId = Cart::getId();

        OwnerContext::withOwner($ownerA, function () use ($cartId, $foreignCustomer): void {
            expect(fn () => app(CheckoutServiceInterface::class)->startCheckout($cartId, $foreignCustomer->id))
                ->toThrow(InvalidCheckoutStateException::class);
        });
    });

    it('accepts a checkout customer owned by the ambient owner', function (): void {
        config()->set('checkout.owner.enabled', true);
        config()->set('checkout.owner.auto_assign_on_create', true);
        config()->set('customers.features.owner.enabled', true);
        config()->set('customers.features.owner.auto_assign_on_create', true);

        $owner = User::factory()->create();

        $ownedCustomer = OwnerContext::withOwner($owner, function (): Customer {
            $customer = Customer::create([
                'first_name' => 'Owned',
                'last_name' => 'Customer',
                'email' => 'owned-scope@example.com',
            ]);
            $customer->forceFill(['is_guest' => true])->save();

            return $customer;
        });

        Cart::setIdentifier('checkout-validation-ok-cart');
        Cart::add('checkout-validation-ok-sku', 'Checkout Item', 1500, 1);
        $cartId = Cart::getId();

        $session = OwnerContext::withOwner($owner, fn (): CheckoutSession => app(CheckoutServiceInterface::class)->startCheckout($cartId, $ownedCustomer->id));

        expect($session->customer_id)->toBe($ownedCustomer->id);
    });

    it('rejects an unknown checkout customer id', function (): void {
        Cart::setIdentifier('checkout-validation-missing-cart');
        Cart::add('checkout-validation-missing-sku', 'Checkout Item', 1500, 1);
        $cartId = Cart::getId();

        expect(fn () => app(CheckoutServiceInterface::class)->startCheckout($cartId, (string) Str::uuid()))
            ->toThrow(InvalidCheckoutStateException::class);
    });
});

describe('mass assignment guard', function (): void {
    it('ignores server-computed fields on create', function (): void {
        $session = CheckoutSession::create([
            'cart_id' => 'mass-assign-guard',
            'customer_id' => 'customer-1',
            'billing_data' => ['email' => 'mass-assign@example.com'],
            'shipping_data' => ['city' => 'Kuala Lumpur'],
            'selected_shipping_method' => 'standard',
            'selected_payment_gateway' => 'chip',
            'subtotal' => 999,
            'grand_total' => 999,
            'status' => Completed::class,
            'owner_type' => 'users',
            'owner_id' => '1',
            'payment_data' => ['evil' => true],
            'order_id' => (string) Str::uuid(),
        ]);

        expect($session->customer_id)->toBe('customer-1')
            ->and($session->billing_data)->toBe(['email' => 'mass-assign@example.com'])
            ->and($session->selected_payment_gateway)->toBe('chip')
            ->and($session->subtotal)->toBe(0)
            ->and($session->grand_total)->toBe(0)
            ->and($session->status)->toBeInstanceOf(Pending::class)
            ->and($session->owner_type)->toBeNull()
            ->and($session->owner_id)->toBeNull()
            ->and($session->payment_data)->toBeNull()
            ->and($session->order_id)->toBeNull();
    });

    it('ignores server-computed fields on update', function (): void {
        $session = CheckoutSession::forceCreate([
            'cart_id' => 'mass-update-guard',
            'subtotal' => 5000,
            'grand_total' => 5000,
        ]);

        $session->update([
            'grand_total' => 0,
            'status' => Completed::class,
            'owner_type' => 'users',
            'owner_id' => '1',
        ]);

        $fresh = $session->fresh();

        expect($fresh->grand_total)->toBe(5000)
            ->and($fresh->status)->toBeInstanceOf(Pending::class)
            ->and($fresh->owner_type)->toBeNull()
            ->and($fresh->owner_id)->toBeNull();
    });
});

describe('stored actor allowlist', function (): void {
    it('rejects a stored actor whose model is not allowlisted', function (): void {
        $address = Address::create([
            'line1' => '1 Allowlist Lane',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]);

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'actor-allowlist-1',
            'payment_data' => [
                'checkout_actor' => [
                    'type' => $address->getMorphClass(),
                    'id' => (string) $address->getKey(),
                ],
            ],
        ]);

        $method = new ReflectionMethod(PersistCustomerStep::class, 'resolveStoredActor');
        $actor = $method->invoke(app(PersistCustomerStep::class), $session);

        expect($actor)->toBeNull();
    });

    it('rejects a stored actor owned by a different owner', function (): void {
        config()->set('checkout.owner.enabled', true);

        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $foreignCustomer = OwnerContext::withOwner($ownerB, function (): Customer {
            $customer = Customer::create([
                'first_name' => 'Foreign',
                'last_name' => 'Actor',
                'email' => 'foreign-actor@example.com',
            ]);
            $customer->forceFill(['is_guest' => true])->save();

            return $customer;
        });

        $session = OwnerContext::withOwner($ownerA, fn (): CheckoutSession => CheckoutSession::forceCreate([
            'cart_id' => 'actor-cross-owner-1',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'payment_data' => [
                'checkout_actor' => [
                    'type' => $foreignCustomer->getMorphClass(),
                    'id' => (string) $foreignCustomer->getKey(),
                ],
            ],
        ]));

        $method = new ReflectionMethod(PersistCustomerStep::class, 'resolveStoredActor');
        $actor = OwnerContext::withOwner($ownerA, fn (): ?Model => $method->invoke(app(PersistCustomerStep::class), $session));

        expect($actor)->toBeNull();
    });

    it('resolves a stored actor owned by the session owner', function (): void {
        config()->set('checkout.owner.enabled', true);

        $owner = User::factory()->create();

        $ownedCustomer = OwnerContext::withOwner($owner, function (): Customer {
            $customer = Customer::create([
                'first_name' => 'Owned',
                'last_name' => 'Actor',
                'email' => 'owned-actor@example.com',
            ]);
            $customer->forceFill(['is_guest' => true])->save();

            return $customer;
        });

        $session = OwnerContext::withOwner($owner, fn (): CheckoutSession => CheckoutSession::forceCreate([
            'cart_id' => 'actor-same-owner-1',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'payment_data' => [
                'checkout_actor' => [
                    'type' => $ownedCustomer->getMorphClass(),
                    'id' => (string) $ownedCustomer->getKey(),
                ],
            ],
        ]));

        $method = new ReflectionMethod(PersistCustomerStep::class, 'resolveStoredActor');
        $actor = OwnerContext::withOwner($owner, fn (): ?Model => $method->invoke(app(PersistCustomerStep::class), $session));

        expect($actor)->not->toBeNull()
            ->and($actor?->is($ownedCustomer))->toBeTrue();
    });

    it('validates actor owner tuples without a database round trip', function (): void {
        $method = new ReflectionMethod(PersistCustomerStep::class, 'actorOwnerConsistent');
        $step = app(PersistCustomerStep::class);

        $sessionA = new CheckoutSession;
        $sessionA->forceFill([
            'owner_type' => 'users',
            'owner_id' => 'owner-a',
        ]);

        $actorB = new Customer;
        $actorB->forceFill([
            'owner_type' => 'users',
            'owner_id' => 'owner-b',
        ]);

        $actorA = new Customer;
        $actorA->forceFill([
            'owner_type' => 'users',
            'owner_id' => 'owner-a',
        ]);

        $globalActor = new Customer;
        $globalActor->forceFill([
            'owner_type' => null,
            'owner_id' => null,
        ]);

        expect($method->invoke($step, $sessionA, $actorB))->toBeFalse()
            ->and($method->invoke($step, $sessionA, $actorA))->toBeTrue()
            ->and($method->invoke($step, $sessionA, $globalActor))->toBeTrue()
            ->and($method->invoke($step, $sessionA, new User))->toBeTrue();
    });
});

describe('configurable callback session param', function (): void {
    it('builds gateway callback urls with the configured session query param', function (): void {
        config()->set('checkout.defaults.session_query_param', 'checkout_session');

        $captured = null;
        $processor = mock(PaymentProcessorInterface::class);
        $processor->shouldReceive('getIdentifier')->andReturn('chip');
        $processor->shouldReceive('isAvailable')->andReturnTrue();
        $processor->shouldReceive('createPayment')
            ->once()
            ->andReturnUsing(function (CheckoutSession $session, PaymentRequest $request) use (&$captured): PaymentResult {
                $captured = $request;

                return PaymentResult::pending('pay_param_1', 'https://gateway.example.test/pay');
            });

        $resolver = mock(PaymentGatewayResolverInterface::class);
        $resolver->shouldReceive('hasGateway')->with('chip')->andReturnTrue();
        $resolver->shouldReceive('resolve')->with('chip')->andReturn($processor);

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'callback-param-cart',
            'status' => Processing::class,
            'selected_payment_gateway' => 'chip',
            'grand_total' => 1000,
            'currency' => 'MYR',
        ]);

        $result = (new ProcessPaymentStep($resolver))->handle($session);

        expect($result->isSuccessful())->toBeTrue()
            ->and($captured)->not->toBeNull()
            ->and($captured->successUrl)->toContain('checkout_session=' . $session->id)
            ->and($captured->failureUrl)->toContain('checkout_session=' . $session->id)
            ->and($captured->cancelUrl)->toContain('checkout_session=' . $session->id);
    });
});

describe('CHIP callback normalization', function (): void {
    it('normalizes CHIP purchase totals and currency into strict types', function (): void {
        $result = app(ChipProcessor::class)->handleCallback([
            'id' => 'purchase_norm_1',
            'event_type' => 'purchase.paid',
            'status' => 'paid',
            'purchase' => ['total' => '1000', 'currency' => 'myr'],
        ]);

        expect($result->status)->toBe(PaymentStatus::Completed)
            ->and($result->amount)->toBe(1000)
            ->and($result->currency)->toBe('MYR')
            ->and($result->paymentId)->toBe('purchase_norm_1');
    });

    it('fails closed on non-integer CHIP totals and invalid currency', function (): void {
        $result = app(ChipProcessor::class)->handleCallback([
            'id' => 'purchase_norm_2',
            'event_type' => 'purchase.paid',
            'status' => 'paid',
            'purchase' => ['total' => 1000.50, 'currency' => 'US'],
        ]);

        expect($result->status)->toBe(PaymentStatus::Completed)
            ->and($result->amount)->toBeNull()
            ->and($result->currency)->toBeNull();
    });

    it('normalizes callback values in the cashier CHIP processor too', function (): void {
        $result = app(CashierChipProcessor::class)->handleCallback([
            'id' => 98765,
            'event_type' => 'purchase.paid',
            'status' => 'paid',
            'purchase' => ['total' => 2500, 'currency' => 'myr'],
        ]);

        expect($result->amount)->toBe(2500)
            ->and($result->currency)->toBe('MYR')
            ->and($result->paymentId)->toBe('98765');
    });
});

describe('repeated failure callbacks', function (): void {
    it('dispatches a single failure event for repeated failure callbacks', function (): void {
        Event::fake([CheckoutFailed::class]);

        $registry = new CheckoutStepRegistry;
        $registry->register('process_payment', regressionTrackStep('process_payment'));
        $registry->register('create_order', regressionTrackStep('create_order', ['process_payment']));
        $registry->setOrder(['process_payment', 'create_order']);

        $service = regressionCheckoutService($registry);
        $handler = new HandleCheckoutPaymentCallback($service, new CheckoutCallbackStatePolicy);

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'repeat-failure-cart',
            'status' => AwaitingPayment::class,
            'selected_payment_gateway' => 'chip',
            'step_states' => ['process_payment' => 'pending', 'create_order' => 'pending'],
        ]);

        $handler->handle($session->getKey(), 'failure');
        $session->forceFill(['error_message' => 'first-failure-marker'])->save();
        $second = $handler->handle($session->getKey(), 'failure');

        expect($second->result?->success)->toBeFalse()
            ->and($session->fresh()->status)->toBeInstanceOf(PaymentFailedState::class)
            ->and($session->fresh()->error_message)->toBe('first-failure-marker');

        Event::assertDispatchedTimes(CheckoutFailed::class, 1);
    });

    it('does not void the same payment twice across compensation runs', function (): void {
        $voidTracker = new class
        {
            /** @var list<string> */
            public array $voided = [];
        };

        $resolver = new PaymentGatewayResolver(defaultGateway: 'repair-stub');
        $resolver->register('repair-stub', regressionStubProcessor(PaymentResult::failed('unused'), $voidTracker));

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'double-void-cart',
            'selected_payment_gateway' => 'repair-stub',
            'payment_id' => 'pay_dup_1',
            'grand_total' => 1500,
            'payment_data' => [
                'payment_id' => 'pay_dup_1',
                'status' => PaymentStatus::Pending->value,
            ],
        ]);

        $step = new ProcessPaymentStep($resolver);
        $first = $step->compensate($session);
        $session->recordCompensation('process_payment', $first);
        $second = $step->compensate($session->fresh() ?? $session);

        expect($first->isCompensated())->toBeTrue()
            ->and($second->isCompensated())->toBeTrue()
            ->and($second->data['skipped'] ?? false)->toBeTrue()
            ->and($voidTracker->voided)->toBe(['pay_dup_1']);
    });

    it('still compensates a new payment created by a retry', function (): void {
        $voidTracker = new class
        {
            /** @var list<string> */
            public array $voided = [];
        };

        $resolver = new PaymentGatewayResolver(defaultGateway: 'repair-stub');
        $resolver->register('repair-stub', regressionStubProcessor(PaymentResult::failed('unused'), $voidTracker));

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'retry-void-cart',
            'selected_payment_gateway' => 'repair-stub',
            'payment_id' => 'pay_retry_new',
            'grand_total' => 1500,
            'payment_data' => [
                'payment_id' => 'pay_retry_new',
                'status' => PaymentStatus::Pending->value,
                'checkout_compensation_log' => [
                    [
                        'step_identifier' => 'process_payment',
                        'status' => 'rolled_back',
                        'message' => 'old payment voided',
                        'data' => ['operation' => 'void', 'payment_id' => 'pay_retry_old'],
                        'errors' => [],
                        'recorded_at' => now()->toIso8601String(),
                    ],
                ],
            ],
        ]);

        $result = (new ProcessPaymentStep($resolver))->compensate($session);

        expect($result->isCompensated())->toBeTrue()
            ->and($voidTracker->voided)->toBe(['pay_retry_new']);
    });
});

describe('pricing priceable resolution', function (): void {
    it('batch-loads priceables with one query per class', function (): void {
        config()->set('checkout.owner.enabled', false);
        config()->set('products.features.owner.enabled', false);

        $products = [];
        $items = [];

        for ($i = 0; $i < 3; $i++) {
            $product = Product::query()->create([
                'name' => 'Batch Product ' . $i,
                'price' => 2500,
                'currency' => 'MYR',
            ]);
            $products[] = $product;
            $items[] = [
                'id' => 'line-' . $i,
                'product_id' => $product->getKey(),
                'price' => 2500,
                'quantity' => 1,
                'associated_model' => ['class' => Product::class, 'id' => $product->getKey()],
            ];
        }

        $calculator = mock(PriceCalculatorInterface::class);
        $calculator->shouldReceive('calculate')
            ->times(3)
            ->andReturn(new PriceResultData(originalPrice: 2500, finalPrice: 2500, discountAmount: 0));
        app()->instance(PriceCalculatorInterface::class, $calculator);

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'batch-pricing-cart',
            'currency' => 'MYR',
            'cart_snapshot' => ['items' => $items],
        ]);

        $productQueries = [];
        DB::listen(function ($query) use (&$productQueries): void {
            if (mb_stripos($query->sql, 'products') !== false) {
                $productQueries[] = $query->sql;
            }
        });

        $result = app(CalculatePricingStep::class)->handle($session);

        expect($result->isSuccessful())->toBeTrue()
            ->and($productQueries)->toHaveCount(1)
            ->and($session->fresh()->subtotal)->toBe(7500);
    });

    it('rejects a definite cross-tenant priceable even with owner mode off', function (): void {
        // Product scoping stays off (set before first Product use, since
        // scope config snapshots at model boot) so the batch load finds the
        // row and the owner-consistency check itself must reject it.
        config()->set('checkout.owner.enabled', false);
        config()->set('products.features.owner.enabled', false);

        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $foreignProduct = Product::query()->forceCreate([
            'name' => 'Foreign Product',
            'price' => 2500,
            'currency' => 'MYR',
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]);

        $calculator = mock(PriceCalculatorInterface::class);
        $calculator->shouldNotReceive('calculate');
        app()->instance(PriceCalculatorInterface::class, $calculator);

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'cross-tenant-pricing-off-cart',
            'currency' => 'MYR',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'cart_snapshot' => [
                'items' => [
                    [
                        'id' => 'line-1',
                        'product_id' => $foreignProduct->getKey(),
                        'price' => 2500,
                        'quantity' => 1,
                        'associated_model' => ['class' => Product::class, 'id' => $foreignProduct->getKey()],
                    ],
                ],
            ],
        ]);

        $result = app(CalculatePricingStep::class)->handle($session);

        expect($result->isSuccessful())->toBeTrue()
            ->and($session->fresh()->subtotal)->toBe(2500);
    });

    it('honors include_global for catalog products under strict owner mode', function (): void {
        config()->set('checkout.owner.enabled', true);
        config()->set('checkout.owner.include_global', true);
        config()->set('products.features.owner.enabled', false);

        $owner = User::factory()->create();

        $globalProduct = Product::query()->create([
            'name' => 'Global Catalog Product',
            'price' => 3000,
            'currency' => 'MYR',
        ]);

        $calculator = mock(PriceCalculatorInterface::class);
        $calculator->shouldReceive('calculate')
            ->once()
            ->andReturn(new PriceResultData(originalPrice: 3000, finalPrice: 3000, discountAmount: 0));
        app()->instance(PriceCalculatorInterface::class, $calculator);

        $session = OwnerContext::withOwner($owner, fn (): CheckoutSession => CheckoutSession::forceCreate([
            'cart_id' => 'include-global-pricing-cart',
            'currency' => 'MYR',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'cart_snapshot' => [
                'items' => [
                    [
                        'id' => 'line-1',
                        'product_id' => $globalProduct->getKey(),
                        'price' => 3000,
                        'quantity' => 1,
                        'associated_model' => ['class' => Product::class, 'id' => $globalProduct->getKey()],
                    ],
                ],
            ],
        ]));

        $result = OwnerContext::withOwner($owner, fn () => app(CalculatePricingStep::class)->handle($session));

        expect($result->isSuccessful())->toBeTrue();
    });

    it('validates priceable owner tuples without a database round trip', function (): void {
        $method = new ReflectionMethod(CalculatePricingStep::class, 'priceableOwnerConsistent');
        $step = app(CalculatePricingStep::class);

        $sessionA = new CheckoutSession;
        $sessionA->forceFill(['owner_type' => 'users', 'owner_id' => 'owner-a']);

        $modelB = new Product;
        $modelB->forceFill(['owner_type' => 'users', 'owner_id' => 'owner-b']);

        $modelA = new Product;
        $modelA->forceFill(['owner_type' => 'users', 'owner_id' => 'owner-a']);

        $globalModel = new Product;
        $globalModel->forceFill(['owner_type' => null, 'owner_id' => null]);

        $globalSession = new CheckoutSession;
        $globalSession->forceFill(['owner_type' => null, 'owner_id' => null]);

        config()->set('checkout.owner.enabled', true);
        config()->set('checkout.owner.include_global', false);

        expect($method->invoke($step, $modelB, $sessionA))->toBeFalse()
            ->and($method->invoke($step, $modelA, $sessionA))->toBeTrue()
            ->and($method->invoke($step, $globalModel, $sessionA))->toBeFalse();

        config()->set('checkout.owner.include_global', true);

        expect($method->invoke($step, $globalModel, $sessionA))->toBeTrue();

        config()->set('checkout.owner.enabled', false);

        expect($method->invoke($step, $modelB, $sessionA))->toBeFalse()
            ->and($method->invoke($step, $globalModel, $sessionA))->toBeTrue()
            ->and($method->invoke($step, $modelB, $globalSession))->toBeTrue();
    });
});

describe('idempotency key per attempt', function (): void {
    it('suffixes the key with the payment attempt count on retries', function (): void {
        $request = new PaymentRequest(
            amount: 1000,
            currency: 'MYR',
            gateway: 'chip',
            description: 'Attempt key test',
            successUrl: 'https://example.com/success',
            failureUrl: 'https://example.com/failure',
            cancelUrl: 'https://example.com/cancel',
        );

        $session = new CheckoutSession;
        $session->id = 'repair-attempt-key';
        $session->payment_attempts = 2;

        $payload = (new ChipPurchasePayloadBuilder)->build($session, $request);

        expect($payload['idempotency_key'])->toBe('repair-attempt-key:attempt:2');

        $session->payment_attempts = 0;
        $firstPayload = (new ChipPurchasePayloadBuilder)->build($session, $request);

        expect($firstPayload['idempotency_key'])->toBe('repair-attempt-key');
    });
});

describe('per-type callback gateway map', function (): void {
    it('accepts failure callbacks when the gateway is only in the failure map', function (): void {
        config()->set('checkout.routes.callbacks.success', []);
        config()->set('checkout.routes.callbacks.failure', ['chip' => 'payment/chip/failure']);
        config()->set('checkout.redirects.failure', '/checkout/failed');

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'asymmetric-callback-cart',
            'status' => Pending::class,
            'selected_payment_gateway' => 'chip',
            'payment_data' => [
                'callback_token' => 'asymmetric-token',
                'callback_token_created_at' => now()->toIso8601String(),
            ],
        ]);

        $route = new Route(['GET'], 'payment/chip/failure', [
            'as' => 'checkout.payment.chip.failure',
            'uses' => static function (): void {},
        ]);
        $request = Request::create('/checkout/payment/chip/failure', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'asymmetric-token',
        ]);
        $request->setRouteResolver(static fn (): Route => $route);

        $response = app(PaymentCallbackController::class)->failure($request);

        expect($response->getTargetUrl())->toContain('/checkout/failed')
            ->and($response->getSession()->get('error'))->toBe('Payment failed')
            ->and($session->fresh()->status)->toBeInstanceOf(PaymentFailedState::class);
    });

    it('rejects callbacks whose type segment is not a known callback type', function (): void {
        config()->set('checkout.redirects.failure', '/checkout/failed');

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'bogus-type-cart',
            'status' => Pending::class,
            'selected_payment_gateway' => 'chip',
            'payment_data' => [
                'callback_token' => 'bogus-type-token',
                'callback_token_created_at' => now()->toIso8601String(),
            ],
        ]);

        $route = new Route(['GET'], 'payment/chip/refund', [
            'as' => 'checkout.payment.chip.refund',
            'uses' => static function (): void {},
        ]);
        $request = Request::create('/checkout/payment/chip/refund', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'bogus-type-token',
        ]);
        $request->setRouteResolver(static fn (): Route => $route);

        $response = app(PaymentCallbackController::class)->failure($request);

        expect($response->getSession()->get('error'))->toBe('Checkout session not found')
            ->and($session->fresh()->status)->toBeInstanceOf(Pending::class);
    });
});

describe('callback rate limiting', function (): void {
    it('does not consume the rate budget on valid callbacks', function (): void {
        config()->set('checkout.payment.callback_rate_limit.max_attempts', 2);
        config()->set('checkout.redirects.success', '/orders/{order_id}');
        config()->set('checkout.redirects.failure', '/checkout/failed');

        $orderId = (string) Str::uuid();
        $session = CheckoutSession::forceCreate([
            'cart_id' => 'rate-budget-cart',
            'status' => Completed::class,
            'completed_at' => now(),
            'order_id' => $orderId,
            'selected_payment_gateway' => 'chip',
            'payment_data' => [
                'callback_token' => 'budget-token',
                'callback_token_created_at' => now()->toIso8601String(),
                'callback_token_consumed_at' => now()->toIso8601String(),
            ],
        ]);

        $controller = app(PaymentCallbackController::class);

        for ($i = 0; $i < 3; $i++) {
            $request = Request::create('/checkout/payment/chip/success', 'GET', [
                'session' => $session->id,
                'checkout_callback_token' => 'budget-token',
            ]);

            $response = $controller->success($request);

            expect($response->getTargetUrl())->toContain('/orders/' . $orderId);
        }
    });

    it('rejects malformed session ids without querying', function (): void {
        config()->set('checkout.redirects.failure', '/checkout/failed');

        $sessionQueries = [];
        DB::listen(function ($query) use (&$sessionQueries): void {
            if (mb_stripos($query->sql, 'checkout_sessions') !== false) {
                $sessionQueries[] = $query->sql;
            }
        });

        $request = Request::create('/checkout/payment/chip/failure', 'GET', [
            'session' => 'not-a-uuid',
            'checkout_callback_token' => 'whatever',
        ]);

        $response = app(PaymentCallbackController::class)->failure($request);

        expect($response->getTargetUrl())->toContain('/checkout/failed')
            ->and($response->getSession()->get('error'))->toBe('Checkout session not found')
            ->and($sessionQueries)->toBe([]);
    });

    it('isolates rate budgets by client ip', function (): void {
        config()->set('checkout.payment.callback_rate_limit.max_attempts', 1);
        config()->set('checkout.payment.callback_rate_limit.decay_seconds', 600);
        config()->set('checkout.redirects.failure', '/checkout/failed');
        config()->set('checkout.redirects.cancel', '/checkout/cancelled');

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'rate-ip-cart',
            'status' => Pending::class,
            'selected_payment_gateway' => 'chip',
            'payment_data' => [
                'callback_token' => 'ip-token',
                'callback_token_created_at' => now()->toIso8601String(),
            ],
        ]);

        $controller = app(PaymentCallbackController::class);

        $attackerRequest = Request::create('/checkout/payment/chip/cancel', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'wrong-token',
        ], server: ['REMOTE_ADDR' => '10.0.0.1']);

        $victimRequest = Request::create('/checkout/payment/chip/cancel', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'ip-token',
        ], server: ['REMOTE_ADDR' => '10.0.0.2']);

        $attackerResponse = $controller->cancel($attackerRequest);
        $victimResponse = $controller->cancel($victimRequest);

        expect($attackerResponse->getSession()->get('error'))->toBe('Checkout session not found')
            ->and($victimResponse->getTargetUrl())->toContain('/checkout/cancelled');
    });
});

describe('single-write transitions', function (): void {
    it('persists each transition with one update and stamps terminal time', function (): void {
        $session = CheckoutSession::forceCreate(['cart_id' => 'transition-count']);

        $updates = [];
        DB::listen(function ($query) use (&$updates): void {
            if (mb_stripos($query->sql, 'update') === 0 && mb_stripos($query->sql, 'checkout_sessions') !== false) {
                $updates[] = $query->sql;
            }
        });

        $session->transitionStatus(Processing::class);

        expect($updates)->toHaveCount(1);

        $session->transitionStatus(Completed::class);
        $fresh = $session->fresh();

        expect($updates)->toHaveCount(2)
            ->and($fresh->status)->toBeInstanceOf(Completed::class)
            ->and($fresh->completed_at)->not->toBeNull();
    });
});

describe('verification failure observability', function (): void {
    it('records unverifiable payments without leaving the retryable state', function (): void {
        Log::spy();

        $registry = new CheckoutStepRegistry;
        $registry->register('process_payment', regressionTrackStep('process_payment'));
        $registry->register('create_order', regressionTrackStep('create_order', ['process_payment']));
        $registry->setOrder(['process_payment', 'create_order']);

        $service = regressionCheckoutService($registry);
        $handler = new HandleCheckoutPaymentCallback($service, new CheckoutCallbackStatePolicy);

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'verify-failure-cart',
            'status' => AwaitingPayment::class,
            'selected_payment_gateway' => 'chip',
            'grand_total' => 1000,
            'currency' => 'MYR',
        ]);

        $outcome = $handler->handle($session->getKey(), 'success', []);
        $fresh = $session->fresh();

        expect($outcome->result?->success)->toBeFalse()
            ->and($fresh->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and($fresh->error_message)->toBe('Payment could not be verified')
            ->and(data_get($fresh->payment_data, 'verification_status'))->toBe(PaymentStatus::Failed->value)
            ->and(data_get($fresh->payment_data, 'verified_at'))->not->toBeNull();

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Checkout payment could not be verified', Mockery::on(
                static fn (array $context): bool => ($context['gateway'] ?? null) === 'chip'
            ));
    });
});

describe('pipeline write coalescing', function (): void {
    it('runs each step with two session writes instead of three', function (): void {
        $registry = new CheckoutStepRegistry;
        $registry->register('repair_a', regressionTrackStep('repair_a'));
        $registry->register('repair_b', regressionTrackStep('repair_b'));
        $registry->register('repair_c', regressionTrackStep('repair_c'));
        $registry->setOrder(['repair_a', 'repair_b', 'repair_c']);

        $session = CheckoutSession::forceCreate(['cart_id' => 'write-count']);

        $updates = [];
        DB::listen(function ($query) use (&$updates): void {
            if (mb_stripos($query->sql, 'update') === 0 && mb_stripos($query->sql, 'checkout_sessions') !== false) {
                $updates[] = $query->sql;
            }
        });

        $result = (new StepExecutor($registry, app(Dispatcher::class)))->run($session);

        expect($result->success)->toBeTrue()
            ->and($updates)->toHaveCount(6);
    });
});

describe('server-sourced redirects', function (): void {
    it('ignores request input when building redirect urls', function (): void {
        config()->set('checkout.redirects.success', '/orders/{order_id}');
        config()->set('checkout.redirects.failure', '/checkout/failed');

        $orderId = (string) Str::uuid();
        $session = CheckoutSession::forceCreate([
            'cart_id' => 'redirect-source-cart',
            'status' => Completed::class,
            'completed_at' => now(),
            'order_id' => $orderId,
            'selected_payment_gateway' => 'chip',
            'payment_data' => [
                'callback_token' => 'redirect-token',
                'callback_token_created_at' => now()->toIso8601String(),
            ],
        ]);

        $request = Request::create('/checkout/payment/chip/success', 'GET', [
            'session' => $session->id,
            'checkout_callback_token' => 'redirect-token',
            'order_id' => 'evil-order-id',
        ]);

        $response = app(PaymentCallbackController::class)->success($request);

        expect($response->getTargetUrl())->toContain('/orders/' . $orderId)
            ->and($response->getTargetUrl())->not->toContain('evil-order-id');
    });
});

describe('pricing join by item id', function (): void {
    it('prices cart lines from matching item ids instead of positions', function (): void {
        config()->set('checkout.create_order.confirm_payment', false);

        $customer = new Customer;
        $customer->forceFill(['id' => 'customer-item-join']);

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'item-join-cart',
            'cart_snapshot' => [
                'items' => [
                    ['id' => 'a', 'name' => 'Item A', 'quantity' => 1, 'price' => 100],
                    ['id' => 'b', 'name' => 'Item B', 'quantity' => 1, 'price' => 200],
                ],
            ],
            'pricing_data' => [
                'items' => [
                    ['item_id' => 'b', 'unit_price' => 200, 'quantity' => 1],
                    ['item_id' => 'a', 'unit_price' => 100, 'quantity' => 1],
                ],
            ],
            'payment_data' => ['type' => 'free_order'],
            'subtotal' => 300,
            'grand_total' => 300,
            'currency' => 'MYR',
        ]);
        $session->setRelation('customer', $customer);

        $cartManager = mock(CartManagerInterface::class);
        $cartManager->shouldReceive('getById')->once()->with($session->cart_id)->andReturnNull();
        app()->instance(CartManagerInterface::class, $cartManager);

        $order = new Order;
        $order->forceFill(['id' => (string) Str::uuid(), 'order_number' => 'ORD-ITEM-JOIN']);

        $capturedItems = null;
        $orderService = mock(OrderServiceInterface::class);
        $orderService->shouldReceive('createOrder')
            ->once()
            ->andReturnUsing(function (array $orderData, array $items) use (&$capturedItems, $order): Order {
                $capturedItems = $items;

                return $order;
            });
        app()->instance(OrderServiceInterface::class, $orderService);

        $result = app(CreateOrderStep::class)->handle($session);

        expect($result->isSuccessful())->toBeTrue()
            ->and($capturedItems[0]['unit_price'])->toBe(100)
            ->and($capturedItems[1]['unit_price'])->toBe(200);
    });
});

describe('incomplete payment reference', function (): void {
    it('fails closed when gateway and transaction references are missing', function (): void {
        config()->set('checkout.create_order.confirm_payment', true);

        $order = new Order;
        $order->forceFill(['id' => (string) Str::uuid(), 'order_number' => 'ORD-REF-MISSING']);

        $orderService = mock(OrderServiceInterface::class);
        $orderService->shouldReceive('createOrder')->once()->andReturn($order);
        $orderService->shouldReceive('confirmPayment')->never();
        app()->instance(OrderServiceInterface::class, $orderService);

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'incomplete-ref-cart',
            'cart_snapshot' => ['items' => []],
            'payment_data' => [
                'type' => 'card',
                'status' => PaymentStatus::Completed->value,
                'amount' => 1000,
                'currency' => 'MYR',
            ],
            'subtotal' => 1000,
            'grand_total' => 1000,
            'currency' => 'MYR',
        ]);
        $session->transitionStatus(Processing::class);

        $result = app(CreateOrderStep::class)->handle($session);
        $fresh = $session->fresh();

        expect($result->isSuccessful())->toBeFalse()
            ->and(data_get($fresh->payment_data, 'reference_reconciliation.status'))->toBe('incomplete')
            ->and($fresh->error_message)->toBe('Payment reference is incomplete and cannot be confirmed.');
    });
});

describe('callback transaction nesting', function (): void {
    it('completes a success callback without nested savepoints', function (): void {
        $registry = new CheckoutStepRegistry;
        $registry->register('process_payment', regressionTrackStep('process_payment'));
        $registry->register('create_order', regressionTrackStep('create_order', ['process_payment']));
        $registry->setOrder(['process_payment', 'create_order']);

        $resolver = new PaymentGatewayResolver(defaultGateway: 'repair-stub');
        $resolver->register('repair-stub', regressionStubProcessor(new PaymentResult(
            status: PaymentStatus::Completed,
            paymentId: 'pay_savepoint_1',
            amount: 1000,
            currency: 'MYR',
        )));

        $service = regressionCheckoutService($registry, $resolver);
        $handler = new HandleCheckoutPaymentCallback($service, new CheckoutCallbackStatePolicy);

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'savepoint-cart',
            'status' => AwaitingPayment::class,
            'selected_payment_gateway' => 'repair-stub',
            'payment_id' => 'pay_savepoint_1',
            'grand_total' => 1000,
            'currency' => 'MYR',
            'step_states' => ['process_payment' => 'pending', 'create_order' => 'pending'],
        ]);

        $begunTransactions = 0;
        DB::connection()->beforeStartingTransaction(function () use (&$begunTransactions): void {
            $begunTransactions++;
        });

        $outcome = $handler->handle($session->getKey(), 'success', ['status' => 'paid']);

        expect($outcome->result?->success)->toBeTrue()
            ->and($session->fresh()->status)->toBeInstanceOf(Completed::class)
            ->and($begunTransactions)->toBe(1);
    });
});

describe('gateway I/O outside transactions', function (): void {
    it('executes the payment gateway call outside any database transaction', function (): void {
        $tracker = new class
        {
            public ?int $transactionLevel = null;
        };

        $processor = mock(PaymentProcessorInterface::class);
        $processor->shouldReceive('getIdentifier')->andReturn('chip');
        $processor->shouldReceive('isAvailable')->andReturnTrue();
        $processor->shouldReceive('createPayment')
            ->once()
            ->andReturnUsing(function (CheckoutSession $session, PaymentRequest $request) use ($tracker): PaymentResult {
                $tracker->transactionLevel = DB::transactionLevel();

                return PaymentResult::success('pay_outside_txn', amount: 1000);
            });

        $gatewayResolver = mock(PaymentGatewayResolverInterface::class);
        $gatewayResolver->shouldReceive('hasGateway')->with('chip')->andReturnTrue();
        $gatewayResolver->shouldReceive('resolve')->with('chip')->andReturn($processor);

        $registry = new CheckoutStepRegistry;
        $registry->register('calculate_shipping', regressionTrackStep('calculate_shipping'));
        $registry->register('process_payment', new ProcessPaymentStep($gatewayResolver));
        $registry->register('create_order', regressionTrackStep('create_order', ['process_payment']));
        $registry->setOrder(['calculate_shipping', 'process_payment', 'create_order']);

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'split-txn-cart',
            'status' => Pending::class,
            'selected_payment_gateway' => 'chip',
            'grand_total' => 1000,
            'currency' => 'MYR',
        ]);

        // The test itself runs inside a transaction; the gateway call must
        // observe that same baseline level, not a deeper pipeline level.
        $baselineLevel = DB::transactionLevel();
        $result = regressionCheckoutService($registry)->processCheckout($session);

        expect($tracker->transactionLevel)->toBe($baselineLevel)
            ->and($result->success)->toBeTrue()
            ->and($session->fresh()->status)->toBeInstanceOf(Completed::class);
    });

    it('stays quiet when a concurrent update interrupts the pipeline after payment', function (): void {
        Event::fake([CheckoutFailed::class, CheckoutCompleted::class]);

        $tracker = new class
        {
            /** @var list<string> */
            public array $executed = [];
        };

        $registry = new CheckoutStepRegistry;
        $registry->register('validate_cart', regressionTrackStep('validate_cart', [], $tracker));
        $registry->register('process_payment', regressionTrackStep(
            'process_payment',
            ['validate_cart'],
            $tracker,
            static function (CheckoutSession $session, string $identifier): StepResult {
                // Simulate a cancel callback winning the race while the
                // gateway call was in flight.
                $session->transitionStatus(Cancelled::class);

                return StepResult::success($identifier);
            },
        ));
        $registry->register('create_order', regressionTrackStep('create_order', ['process_payment'], $tracker));
        $registry->setOrder(['validate_cart', 'process_payment', 'create_order']);

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'interrupted-cart',
            'status' => Pending::class,
            'selected_payment_gateway' => 'chip',
            'grand_total' => 1000,
            'currency' => 'MYR',
        ]);

        $result = regressionCheckoutService($registry)->processCheckout($session);

        expect($result->success)->toBeFalse()
            ->and($result->errors)->toHaveKey('interrupted')
            ->and($tracker->executed)->not->toContain('create_order')
            ->and($session->fresh()->status)->toBeInstanceOf(Cancelled::class);

        Event::assertNotDispatched(CheckoutFailed::class);
        Event::assertNotDispatched(CheckoutCompleted::class);
    });

    it('voids a payment orphaned by a concurrent update after payment', function (): void {
        $voidTracker = new class
        {
            /** @var list<string> */
            public array $voided = [];
        };

        $tracker = new class
        {
            /** @var list<string> */
            public array $executed = [];
        };

        $registry = new CheckoutStepRegistry;
        $registry->register('validate_cart', regressionTrackStep('validate_cart', [], $tracker));
        $registry->register('process_payment', regressionTrackStep(
            'process_payment',
            ['validate_cart'],
            $tracker,
            static function (CheckoutSession $session, string $identifier): StepResult {
                // This run created pay_orphan_2, then a concurrent callback
                // overwrote the stored payment and cancelled the session.
                $session->forceFill(['payment_id' => 'pay_orphan_2'])->save();
                DB::table($session->getTable())
                    ->where($session->getKeyName(), $session->getKey())
                    ->update(['payment_id' => 'pay_other_1']);
                $session->transitionStatus(Cancelled::class);

                return StepResult::success($identifier);
            },
        ));
        $registry->register('create_order', regressionTrackStep('create_order', ['process_payment'], $tracker));
        $registry->setOrder(['validate_cart', 'process_payment', 'create_order']);

        $paymentResolver = new PaymentGatewayResolver(defaultGateway: 'repair-stub');
        $paymentResolver->register('repair-stub', regressionStubProcessor(PaymentResult::failed('unused'), $voidTracker));

        $session = CheckoutSession::forceCreate([
            'cart_id' => 'orphan-void-cart',
            'status' => Pending::class,
            'selected_payment_gateway' => 'repair-stub',
            'grand_total' => 1000,
            'currency' => 'MYR',
        ]);

        $result = regressionCheckoutService($registry, $paymentResolver)->processCheckout($session);

        expect($result->success)->toBeFalse()
            ->and($result->errors)->toHaveKey('interrupted')
            ->and($voidTracker->voided)->toBe(['pay_orphan_2'])
            ->and($tracker->executed)->not->toContain('create_order');
    });
});

describe('resumeCheckout id validation', function (): void {
    it('rejects a malformed session id without querying the database', function (): void {
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        expect(fn () => app(CheckoutServiceInterface::class)->resumeCheckout('not-a-uuid'))
            ->toThrow(InvalidCheckoutStateException::class)
            ->and($queries)->toBe(0);
    });

    it('rejects an unknown well-formed session id', function (): void {
        expect(fn () => app(CheckoutServiceInterface::class)->resumeCheckout((string) Str::uuid()))
            ->toThrow(InvalidCheckoutStateException::class);
    });
});
