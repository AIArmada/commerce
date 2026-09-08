<?php

declare(strict_types=1);

use AIArmada\Checkout\Actions\CheckoutFinalizer;
use AIArmada\Checkout\Actions\HandleCheckoutPaymentCallback;
use AIArmada\Checkout\Actions\ProcessCheckoutPaymentNotification;
use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Integrations\Payment\ChipProcessor;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use AIArmada\Checkout\Services\StepExecutor;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\Support\CheckoutCallbackStatePolicy;
use AIArmada\Checkout\Support\CheckoutNotificationCallbackResolver;
use AIArmada\Checkout\Support\ChipIntegrationRegistrar;
use AIArmada\Checkout\Support\ChipPaymentStatusMapper;
use AIArmada\Checkout\Support\ChipPurchasePayloadBuilder;
use AIArmada\Checkout\Support\ChipRefundGateway;
use AIArmada\Checkout\Support\HandleChipPurchaseEventForCheckout;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * ADVERSARY PROOF — Attack 4: a `paid` event arriving after a `failure`
 * event for the same purchase is dropped — paid-but-unconfirmed.
 *
 * Both deliveries (chip domain events via `ChipIntegrationRegistrar`, and
 * the checkout webhook route via `ProcessCheckoutWebhook`) funnel into
 * `HandleCheckoutPaymentCallback`, whose `CheckoutCallbackStatePolicy`
 * only treats `Completed` as terminal-idempotent. Success-then-failure is
 * (correctly) absorbed, but failure-then-paid is also dropped: once the
 * session sits in `PaymentFailed`, a later authentic `PurchasePaid` for the
 * SAME purchase fails `canHandleCallback()` and is silently dropped. There
 * is no paid-wins precedence and no terminal reconciliation, so money taken
 * at the gateway never completes the session (it stays `PaymentFailed`). Webhook/event redelivery
 * races make the losing order nondeterministic.
 *
 * No package logic is mocked: real events, real registrar listener, real
 * notification/callback/service chain, real `ChipProcessor`; tracked stub
 * steps only to avoid unrelated integrations.
 */

function adversaryDualDeliveryStep(string $identifier, array $dependencies = []): CheckoutStepInterface
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

/**
 * @return array<string, mixed>
 */
function adversaryChipEventPayload(string $sessionId, string $eventType, string $status): array
{
    $timestamp = 1704067200;

    return [
        'id' => 'purchase_adv_dual',
        'event_type' => $eventType,
        'type' => 'purchase',
        'created_on' => $timestamp,
        'updated_on' => $timestamp,
        'reference' => $sessionId,
        'client' => ['email' => 'checkout@example.com'],
        'purchase' => [
            'total' => 1000,
            'currency' => 'MYR',
            'products' => [[
                'name' => 'Checkout Product',
                'price' => 1000,
                'quantity' => 1,
            ]],
        ],
        'brand_id' => 'brand-123',
        'status' => $status,
    ];
}

it('reconciles a paid event that arrives after a failure event for the same purchase', function (): void {
    $registry = new CheckoutStepRegistry;
    $registry->register('process_payment', adversaryDualDeliveryStep('process_payment'));
    $registry->register('create_order', adversaryDualDeliveryStep('create_order', ['process_payment']));
    $registry->setOrder(['process_payment', 'create_order']);

    $statusMapper = new ChipPaymentStatusMapper;
    $resolver = new PaymentGatewayResolver(defaultGateway: 'chip');
    $resolver->register('chip', new ChipProcessor(
        new ChipPurchasePayloadBuilder,
        $statusMapper,
        new ChipRefundGateway($statusMapper),
    ));

    $service = new CheckoutService(
        stepRegistry: $registry,
        events: app(Dispatcher::class),
        stepExecutor: new StepExecutor($registry, app(Dispatcher::class)),
        finalizer: new CheckoutFinalizer(app(Dispatcher::class)),
        paymentResolver: $resolver,
    );

    $session = CheckoutSession::create([
        'cart_id' => 'adv-dual-delivery',
        'status' => AwaitingPayment::class,
        'selected_payment_gateway' => 'chip',
        'payment_id' => 'purchase_adv_dual',
        'currency' => 'MYR',
        'subtotal' => 1000,
        'grand_total' => 1000,
        'step_states' => [
            'process_payment' => 'pending',
            'create_order' => 'pending',
        ],
        'payment_data' => [
            'callback_token' => 'adv-dual-token',
            'status' => PaymentStatus::Pending->value,
            'amount' => 1000,
            'currency' => 'MYR',
        ],
    ]);

    $sessionId = $session->getKey();

    $listener = new HandleChipPurchaseEventForCheckout(
        new ProcessCheckoutPaymentNotification(
            new HandleCheckoutPaymentCallback($service, new CheckoutCallbackStatePolicy),
            new CheckoutNotificationCallbackResolver,
        ),
    );

    // Failure is processed first (session -> PaymentFailed, compensation runs).
    $listener->handle(PurchasePaymentFailure::fromPayload(
        adversaryChipEventPayload($sessionId, 'purchase.payment_failure', 'error'),
    ));

    // The same purchase is then genuinely paid at the gateway.
    $listener->handle(PurchasePaid::fromPayload(
        adversaryChipEventPayload($sessionId, 'purchase.paid', 'paid'),
    ));

    $finalStatus = $session->fresh()->status;

    expect($finalStatus)->toBeInstanceOf(Completed::class, 'Authentic paid event after a failure never completes the session: money taken, order unconfirmed.');
});
