<?php

declare(strict_types=1);

use AIArmada\Checkout\Actions\CheckoutFinalizer;
use AIArmada\Checkout\Actions\HandleCheckoutPaymentCallback;
use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Integrations\Payment\CashierProcessor;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use AIArmada\Checkout\Services\StepExecutor;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\Support\CheckoutCallbackStatePolicy;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * ADVERSARY PROOF — Attack 2: the cashier callback path confirms a short
 * gateway total at full value.
 *
 * `CashierProcessor::handleCallback()` maps a "completed" payload to
 * `PaymentStatus::Completed` while returning amount=null, currency=null —
 * whatever the gateway declared is dropped. `verifyAndCompletePayment()`
 * then treats status-alone as verified and merges `amount ?? stored
 * grand_total` (self-reported) while wiping `currency` to null, so the
 * downstream `CreateOrderStep::confirmPayment()` compares the session
 * against ITSELF. A gateway that settled 500 on a 1000 session is recorded
 * as 1000 and confirmed at 1000.
 *
 * No package logic is mocked: real service, real resolver, real
 * `CashierProcessor`, tracked stub steps only to avoid unrelated
 * integrations.
 */
function adversaryCallbackTrackedStep(string $identifier, array $dependencies = []): CheckoutStepInterface
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

it('records the gateway-declared amount instead of self-confirming the session total', function (): void {
    $registry = new CheckoutStepRegistry;
    $registry->register('process_payment', adversaryCallbackTrackedStep('process_payment'));
    $registry->register('create_order', adversaryCallbackTrackedStep('create_order', ['process_payment']));
    $registry->setOrder(['process_payment', 'create_order']);

    $resolver = new PaymentGatewayResolver(defaultGateway: 'cashier');
    $resolver->register('cashier', new CashierProcessor);

    $service = new CheckoutService(
        stepRegistry: $registry,
        events: app(Dispatcher::class),
        stepExecutor: new StepExecutor($registry, app(Dispatcher::class)),
        finalizer: new CheckoutFinalizer(app(Dispatcher::class)),
        paymentResolver: $resolver,
    );

    $session = CheckoutSession::create([
        'cart_id' => 'adv-cashier-callback',
        'status' => AwaitingPayment::class,
        'selected_payment_gateway' => 'cashier',
        'payment_id' => 'pay_adv_cashier_short',
        'currency' => 'MYR',
        'subtotal' => 1000,
        'grand_total' => 1000,
        'step_states' => [
            'process_payment' => 'pending',
            'create_order' => 'pending',
        ],
        'payment_data' => [
            'callback_token' => 'adv-callback-token',
            'status' => PaymentStatus::Pending->value,
            'amount' => 1000,
            'currency' => 'MYR',
        ],
    ]);

    // The gateway settled 500 of the 1000 session total.
    $payload = [
        'id' => 'pay_adv_cashier_short',
        'status' => 'completed',
        'amount' => 500,
        'currency' => 'MYR',
        'provider' => 'chip',
    ];

    $handler = new HandleCheckoutPaymentCallback($service, new CheckoutCallbackStatePolicy);
    $outcome = $handler->handle($session->getKey(), 'success', $payload);

    expect($outcome->sessionNotFound)->toBeFalse();

    $recordedAmount = data_get($session->fresh()->payment_data, 'amount');

    expect($recordedAmount)->toBe(500, 'Short gateway total (500) was recorded as the full session total (1000): under-charge confirms.');
});
