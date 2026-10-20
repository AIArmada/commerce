<?php

declare(strict_types=1);

use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentFailureOrigin;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Events\CheckoutPaymentCompleted;
use AIArmada\Checkout\Events\CheckoutStepCompleted;
use AIArmada\Checkout\Events\CheckoutStepFailed;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentAttemptManager;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use AIArmada\Checkout\Services\StepExecutor;
use AIArmada\Checkout\States\Cancelled;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\mock;

beforeEach(function (): void {
    Model::clearBootedModels();
});

it('rejects stale same-generation creation results after recovery has committed', function (PaymentResult $staleResult): void {
    $manager = app(PaymentAttemptManager::class);
    $session = CheckoutSession::forceCreate([
        'cart_id' => 'revision-fence-stale-result',
        'status' => Processing::class,
        'selected_payment_gateway' => 'chip',
        'grand_total' => 1000,
        'currency' => 'MYR',
        'step_states' => ['process_payment' => StepStatus::Processing->value],
    ]);

    $oldClaim = $manager->claim($session, 'chip');
    $recovery = $session->fresh();
    $paymentData = $recovery->payment_data ?? [];
    $paymentData['retry_requested_for_attempt'] = (int) $recovery->payment_attempts;
    $recovery->persistState(['payment_data' => $paymentData]);

    $recoveredClaim = $manager->claim($recovery, 'chip');
    $recoveredOrigin = $manager->record($recovery, $recoveredClaim, new PaymentResult(
        status: PaymentStatus::Completed,
        paymentId: 'pay_recovered_paid',
        transactionId: 'txn_recovered_paid',
        amount: 1000,
        currency: 'MYR',
        gatewayResponse: ['receipt' => 'recovered'],
        provider: 'chip',
    ));
    $recovery->setStepState('process_payment', StepStatus::Completed);

    expect($recoveredOrigin)->not->toBeNull()
        ->and($oldClaim->revision)->toBeLessThan($recoveredClaim->revision);

    $winningSession = $session->fresh();
    $winningPaymentData = $winningSession->payment_data;
    $rejected = $manager->record($session, $oldClaim, $staleResult);
    $fresh = $session->fresh();

    expect($rejected)->toBeNull()
        ->and($fresh->status)->toBeInstanceOf(Processing::class)
        ->and($fresh->payment_id)->toBe('pay_recovered_paid')
        ->and($fresh->step_states)->toBe($winningSession->step_states)
        ->and($fresh->payment_data)->toBe($winningPaymentData)
        ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.payment_id'))->toBe('pay_recovered_paid')
        ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.status'))->toBe(PaymentStatus::Completed->value)
        ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.uncertain'))->toBeFalse()
        ->and(data_get($fresh->payment_data, 'provider'))->toBe('chip')
        ->and(data_get($fresh->payment_data, 'amount'))->toBe(1000)
        ->and(data_get($fresh->payment_data, 'currency'))->toBe('MYR')
        ->and(data_get($fresh->payment_data, 'gateway_response.receipt'))->toBe('recovered')
        ->and(data_get($fresh->payment_data, 'payment_attempt_revision'))->toBe(
            data_get($winningPaymentData, 'payment_attempt_revision'),
        );
})->with([
    'uncertain with no payment id' => [PaymentResult::uncertain('Old create response timed out', provider: 'chip')],
    'failed with a distinct payment id' => [new PaymentResult(
        status: PaymentStatus::Failed,
        paymentId: 'pay_obsolete_failed',
        amount: 1000,
        currency: 'MYR',
        provider: 'chip',
    )],
    'pending with a distinct payment id' => [PaymentResult::pending(
        'pay_obsolete_pending',
        'https://gateway.example.test/old',
        'chip',
    )],
    'processing with the recovered payment id' => [PaymentResult::processing('pay_recovered_paid', 'chip')],
    'successful with the recovered payment id' => [new PaymentResult(
        status: PaymentStatus::Completed,
        paymentId: 'pay_recovered_paid',
        amount: 1000,
        currency: 'MYR',
        provider: 'chip',
    )],
    'successful with a distinct payment id' => [new PaymentResult(
        status: PaymentStatus::Completed,
        paymentId: 'pay_obsolete_paid',
        amount: 1000,
        currency: 'MYR',
        provider: 'chip',
    )],
]);

it('does not begin a step when the captured origin has been superseded by cancellation', function (): void {
    $manager = app(PaymentAttemptManager::class);
    $session = CheckoutSession::forceCreate([
        'cart_id' => 'revision-fence-terminal-step',
        'status' => Processing::class,
        'step_states' => ['calculate_shipping' => StepStatus::Pending->value],
        'current_step' => 'calculate_shipping',
    ]);
    $origin = $manager->captureFailureOrigin($session);

    $session->transitionStatus(Cancelled::class);

    expect($manager->beginStepIfOwned($session, 'calculate_shipping', $origin))->toBeFalse()
        ->and($session->fresh()->step_states)->toBe(['calculate_shipping' => StepStatus::Pending->value])
        ->and($session->fresh()->current_step)->toBe('calculate_shipping');
});

it('uses an accepted payment result origin for its owning step completion', function (): void {
    Event::fake([CheckoutStepCompleted::class, CheckoutStepFailed::class]);

    $manager = app(PaymentAttemptManager::class);
    $processor = revisionFenceProcessor(PaymentResult::success(
        'pay_revision_fence_owner',
        amount: 1000,
        provider: 'chip',
    ));
    $resolver = mock(PaymentGatewayResolverInterface::class);
    $resolver->shouldReceive('hasGateway')->with('chip')->andReturnTrue();
    $resolver->shouldReceive('resolve')->with('chip')->andReturn($processor);
    $resolver->shouldReceive('getDefaultGateway')->andReturn('chip');

    $step = new ProcessPaymentStep($resolver, $manager);
    $executor = new StepExecutor(new CheckoutStepRegistry, app(Dispatcher::class), $manager);
    $session = revisionFenceProcessingSession('revision-fence-owner-result');
    $origin = $manager->captureFailureOrigin($session);

    $result = $executor->processStep($session, $step, $origin);
    $fresh = $session->fresh();

    expect($result->isSuccessful())->toBeTrue()
        ->and($fresh->getStepState('process_payment'))->toBe(StepStatus::Completed)
        ->and($fresh->payment_id)->toBe('pay_revision_fence_owner');

    Event::assertDispatched(CheckoutStepCompleted::class);
    Event::assertNotDispatched(CheckoutStepFailed::class);
});

it('preserves the new claim origin when its frozen gateway is unavailable', function (): void {
    $manager = app(PaymentAttemptManager::class);
    $session = revisionFenceProcessingSession('revision-fence-new-gateway-unavailable');
    $oldClaim = $manager->claim($session, 'old-gateway', 'old-provider');
    $session = $session->fresh();
    $session->persistState(['selected_payment_gateway' => 'new-gateway']);
    $paymentData = $session->payment_data ?? [];
    $paymentData['retry_requested_for_attempt'] = (int) $session->payment_attempts;
    $session->persistState(['payment_data' => $paymentData]);
    $adoptedClaim = $manager->claim($session, 'old-gateway', 'old-provider');

    $resolver = new PaymentGatewayResolver(defaultGateway: 'new-gateway');
    $resolver->register('old-gateway', revisionFenceProcessor(
        PaymentResult::failed('unused'),
        onAvailable: static function (CheckoutSession $session) use ($manager, $adoptedClaim): void {
            $manager->record($session, $adoptedClaim, PaymentResult::failed('Old attempt failed'));
        },
    ));
    $resolver->register('new-gateway', revisionFenceProcessor(PaymentResult::failed('unused'), available: false));

    $step = new ProcessPaymentStep($resolver, $manager);
    $executor = new StepExecutor(new CheckoutStepRegistry, app(Dispatcher::class), $manager);
    $entryOrigin = PaymentFailureOrigin::fromClaim($adoptedClaim);
    $result = $executor->processStep($session, $step, $entryOrigin);
    $fresh = $session->fresh();

    expect($oldClaim->number)->toBe(1)
        ->and($adoptedClaim->revision)->toBeGreaterThan($oldClaim->revision)
        ->and($result->isObserved())->toBeFalse()
        ->and($result->isSuccessful())->toBeFalse()
        ->and($result->failureOrigin?->attempts)->toBe(2)
        ->and($result->executionOrigin?->attempts)->toBe(2)
        ->and($result->executionOrigin?->revision)->toBe(data_get($fresh->payment_data, 'payment_attempt_revision'))
        ->and($fresh->payment_attempts)->toBe(2)
        ->and($fresh->getStepState('process_payment'))->toBe(StepStatus::Failed);
});

it('rejects a payment step write when a new revision arrives after result acceptance', function (): void {
    Event::fake([CheckoutStepCompleted::class, CheckoutStepFailed::class]);

    $manager = app(PaymentAttemptManager::class);
    $processor = revisionFenceProcessor(PaymentResult::success(
        'pay_revision_fence_callback',
        amount: 1000,
        provider: 'chip',
    ));
    $resolver = mock(PaymentGatewayResolverInterface::class);
    $resolver->shouldReceive('hasGateway')->with('chip')->andReturnTrue();
    $resolver->shouldReceive('resolve')->with('chip')->andReturn($processor);
    $resolver->shouldReceive('getDefaultGateway')->andReturn('chip');

    $receivedPaymentEvent = false;
    Event::listen(CheckoutPaymentCompleted::class, function (CheckoutPaymentCompleted $event) use ($manager, &$receivedPaymentEvent): void {
        $receivedPaymentEvent = true;
        $manager->recordVerifiedOutcome($event->session->fresh(), new PaymentResult(
            status: PaymentStatus::Completed,
            paymentId: 'pay_revision_fence_callback',
            amount: 1000,
            currency: 'MYR',
            provider: 'chip',
        ));
    });

    $step = new ProcessPaymentStep($resolver, $manager);
    $executor = new StepExecutor(new CheckoutStepRegistry, app(Dispatcher::class), $manager);
    $session = revisionFenceProcessingSession('revision-fence-callback-after-result');
    $origin = $manager->captureFailureOrigin($session);

    $result = $executor->processStep($session, $step, $origin);
    $fresh = $session->fresh();

    expect($receivedPaymentEvent)->toBeTrue()
        ->and($result->isObserved())->toBeTrue()
        ->and($fresh->status)->toBeInstanceOf(Processing::class)
        ->and($fresh->payment_id)->toBe('pay_revision_fence_callback')
        ->and($fresh->getStepState('process_payment'))->toBe(StepStatus::Processing)
        ->and(data_get($fresh->payment_data, 'payment_attempt_revision'))->toBe(3);

    Event::assertNotDispatched(CheckoutStepCompleted::class);
    Event::assertNotDispatched(CheckoutStepFailed::class);
});

function revisionFenceProcessingSession(string $cartId): CheckoutSession
{
    return CheckoutSession::forceCreate([
        'cart_id' => $cartId,
        'status' => Processing::class,
        'selected_payment_gateway' => 'chip',
        'grand_total' => 1000,
        'currency' => 'MYR',
        'step_states' => [
            'calculate_shipping' => StepStatus::Completed->value,
            'process_payment' => StepStatus::Pending->value,
        ],
    ]);
}

function revisionFenceProcessor(
    PaymentResult $result,
    bool $available = true,
    ?Closure $onAvailable = null,
): PaymentProcessorInterface {
    return new class($result, $available, $onAvailable) implements PaymentProcessorInterface
    {
        public function __construct(
            private readonly PaymentResult $result,
            private readonly bool $available,
            private readonly ?Closure $onAvailable,
        ) {}

        public function getIdentifier(): string
        {
            return 'chip';
        }

        public function getName(): string
        {
            return 'Revision fence processor';
        }

        public function isAvailable(CheckoutSession $session): bool
        {
            if ($this->onAvailable !== null) {
                ($this->onAvailable)($session);
            }

            return $this->available;
        }

        public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
        {
            return $this->result;
        }

        public function handleCallback(array $payload): PaymentResult
        {
            return $this->result;
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
            return $this->result;
        }
    };
}
