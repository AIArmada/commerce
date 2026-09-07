<?php

declare(strict_types=1);

use AIArmada\Checkout\Actions\CheckoutFinalizer;
use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\PaymentCompensationInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\StepExecutor;
use AIArmada\Checkout\States\PaymentFailed;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use Illuminate\Contracts\Events\Dispatcher;

use function Pest\Laravel\mock;

describe('checkout compensation', function (): void {
    it('compensates every executed position in reverse order when a step returns a failure', function (int $failAt): void {
        $identifiers = ['step_1', 'step_2', 'step_3'];
        $tracker = new class
        {
            /** @var list<string> */
            public array $compensated = [];
        };

        $registry = new CheckoutStepRegistry;

        foreach ($identifiers as $index => $identifier) {
            $registry->register($identifier, new class($identifier, $index + 1 === $failAt, $tracker, $index === 0 ? null : $identifiers[$index - 1]) implements CheckoutStepInterface
            {
                public function __construct(
                    private readonly string $identifier,
                    private readonly bool $fails,
                    private readonly object $tracker,
                    private readonly ?string $dependency,
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
                    return $this->fails
                        ? StepResult::failed($this->identifier, 'Intentional failure')
                        : StepResult::success($this->identifier);
                }

                public function canSkip(CheckoutSession $session): bool
                {
                    return false;
                }

                public function compensate(CheckoutSession $session): StepResult
                {
                    $this->tracker->compensated[] = $this->identifier;

                    return StepResult::compensated($this->identifier, 'Compensated in test', [
                        'operation' => 'test_compensation',
                    ]);
                }

                public function getDependencies(): array
                {
                    return $this->dependency === null ? [] : [$this->dependency];
                }
            });
        }

        $registry->setOrder($identifiers);
        $session = CheckoutSession::create([
            'cart_id' => 'checkout-compensation-failure-' . $failAt,
            'step_states' => array_fill_keys($identifiers, StepStatus::Pending->value),
        ]);
        $service = checkoutCompensationService($registry);

        $result = $service->processCheckout($session);

        $expected = array_reverse(array_slice($identifiers, 0, $failAt));
        $freshSession = $session->fresh();

        expect($result->success)->toBeFalse()
            ->and($freshSession->status)->toBeInstanceOf(PaymentFailed::class)
            ->and($tracker->compensated)->toBe($expected)
            ->and(array_column($freshSession->getCompensationLog(), 'step_identifier'))->toBe($expected);
    })->with([2, 3]);

    it('compensates completed work after a step throws and keeps the audit trail after transaction rollback', function (): void {
        $tracker = new class
        {
            /** @var list<string> */
            public array $compensated = [];
        };

        $registry = new CheckoutStepRegistry;
        $registry->register('step_1', new class($tracker) implements CheckoutStepInterface
        {
            public function __construct(private readonly object $tracker) {}

            public function getIdentifier(): string
            {
                return 'step_1';
            }

            public function getName(): string
            {
                return 'Step 1';
            }

            public function validate(CheckoutSession $session): array
            {
                return [];
            }

            public function handle(CheckoutSession $session): StepResult
            {
                return StepResult::success($this->getIdentifier());
            }

            public function canSkip(CheckoutSession $session): bool
            {
                return false;
            }

            public function compensate(CheckoutSession $session): StepResult
            {
                $this->tracker->compensated[] = $this->getIdentifier();

                return StepResult::compensated($this->getIdentifier());
            }

            public function getDependencies(): array
            {
                return [];
            }
        });
        $registry->register('step_2', new class($tracker) implements CheckoutStepInterface
        {
            public function __construct(private readonly object $tracker) {}

            public function getIdentifier(): string
            {
                return 'step_2';
            }

            public function getName(): string
            {
                return 'Step 2';
            }

            public function validate(CheckoutSession $session): array
            {
                return [];
            }

            public function handle(CheckoutSession $session): StepResult
            {
                throw new RuntimeException('Step 2 exploded');
            }

            public function canSkip(CheckoutSession $session): bool
            {
                return false;
            }

            public function compensate(CheckoutSession $session): StepResult
            {
                $this->tracker->compensated[] = $this->getIdentifier();

                return StepResult::compensated($this->getIdentifier());
            }

            public function getDependencies(): array
            {
                return ['step_1'];
            }
        });
        $registry->setOrder(['step_1', 'step_2']);

        $session = CheckoutSession::create([
            'cart_id' => 'checkout-compensation-exception',
            'step_states' => [
                'step_1' => StepStatus::Pending->value,
                'step_2' => StepStatus::Pending->value,
            ],
        ]);

        expect(fn (): mixed => checkoutCompensationService($registry)->processCheckout($session))
            ->toThrow(RuntimeException::class, 'Step 2 exploded');

        $freshSession = $session->fresh();

        expect($freshSession->status)->toBeInstanceOf(PaymentFailed::class)
            ->and($tracker->compensated)->toBe(['step_2', 'step_1'])
            ->and(array_column($freshSession->getCompensationLog(), 'step_identifier'))->toBe(['step_2', 'step_1'])
            ->and($freshSession->getStepState('step_1'))->toBe(StepStatus::RolledBack)
            ->and($freshSession->getStepState('step_2'))->toBe(StepStatus::RolledBack);
    });

    it('refunds completed payments and voids incomplete payments through the compensation contract', function (): void {
        $processor = new class implements PaymentCompensationInterface, PaymentProcessorInterface
        {
            public int $refundCount = 0;

            public int $voidCount = 0;

            public function getIdentifier(): string
            {
                return 'test';
            }

            public function getName(): string
            {
                return 'Test';
            }

            public function isAvailable(CheckoutSession $session): bool
            {
                return true;
            }

            public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
            {
                return PaymentResult::processing('payment');
            }

            public function handleCallback(array $payload): PaymentResult
            {
                return PaymentResult::failed('Not used');
            }

            public function getRedirectUrl(CheckoutSession $session): ?string
            {
                return null;
            }

            public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
            {
                $this->refundCount++;

                return new PaymentResult(PaymentStatus::Refunded, paymentId: $paymentId, amount: $amount);
            }

            public function voidPayment(string $paymentId, ?string $reason = null): PaymentResult
            {
                $this->voidCount++;

                return new PaymentResult(PaymentStatus::Cancelled, paymentId: $paymentId);
            }

            public function checkStatus(string $paymentId): PaymentResult
            {
                return PaymentResult::processing($paymentId);
            }
        };
        $resolver = mock(PaymentGatewayResolverInterface::class);
        $resolver->shouldReceive('resolve')->twice()->with('test')->andReturn($processor);
        $step = new ProcessPaymentStep($resolver);

        $completed = CheckoutSession::create([
            'cart_id' => 'checkout-compensation-refund',
            'selected_payment_gateway' => 'test',
            'payment_id' => 'payment-completed',
            'grand_total' => 1500,
            'payment_data' => [
                'payment_id' => 'payment-completed',
                'status' => PaymentStatus::Completed->value,
            ],
        ]);
        $pending = CheckoutSession::create([
            'cart_id' => 'checkout-compensation-void',
            'selected_payment_gateway' => 'test',
            'payment_id' => 'payment-pending',
            'grand_total' => 1500,
            'payment_data' => [
                'payment_id' => 'payment-pending',
                'status' => PaymentStatus::Pending->value,
            ],
        ]);

        expect($step->compensate($completed)->isCompensated())->toBeTrue()
            ->and($step->compensate($pending)->isCompensated())->toBeTrue()
            ->and($processor->refundCount)->toBe(1)
            ->and($processor->voidCount)->toBe(1);
    });
});

function checkoutCompensationService(CheckoutStepRegistry $registry): CheckoutService
{
    return new CheckoutService(
        stepRegistry: $registry,
        events: app(Dispatcher::class),
        stepExecutor: new StepExecutor($registry, app(Dispatcher::class)),
        finalizer: new CheckoutFinalizer(app(Dispatcher::class)),
        paymentResolver: null,
    );
}
