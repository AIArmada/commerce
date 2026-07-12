<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Services;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Actions\FinalizeCheckoutSession;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\SessionDataTransformerInterface;
use AIArmada\Checkout\Data\CheckoutResult;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Events\CheckoutCancelled;
use AIArmada\Checkout\Events\CheckoutFailed;
use AIArmada\Checkout\Events\CheckoutPaymentCompleted;
use AIArmada\Checkout\Events\CheckoutStarted;
use AIArmada\Checkout\Exceptions\CheckoutStepException;
use AIArmada\Checkout\Exceptions\InvalidCheckoutStateException;
use AIArmada\Checkout\Exceptions\PaymentException;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Cancelled;
use AIArmada\Checkout\States\PaymentFailed;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Transformers\NullSessionDataTransformer;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class CheckoutService implements CheckoutServiceInterface
{
    public function __construct(
        private readonly CheckoutStepRegistryInterface $stepRegistry,
        private readonly Dispatcher $events,
        private readonly StepExecutor $stepExecutor,
        private readonly FinalizeCheckoutSession $finalizer,
        private readonly ?PaymentGatewayResolverInterface $paymentResolver = null,
    ) {}

    public function startCheckout(string $cartId, ?string $customerId = null): CheckoutSession
    {
        $cart = $this->resolveCart($cartId);

        if ($cart === null) {
            throw InvalidCheckoutStateException::cartNotFound($cartId);
        }

        if ($cart->isEmpty()) {
            throw InvalidCheckoutStateException::emptyCart($cartId);
        }

        $session = CheckoutSession::create([
            'cart_id' => $cartId,
            'customer_id' => $customerId,
            'status' => Pending::class,
            'cart_snapshot' => $this->createCartSnapshot($cart),
            'step_states' => $this->initializeStepStates(),
            'current_step' => $this->getFirstStepIdentifier(),
            'expires_at' => now()->addSeconds(config('checkout.defaults.session_ttl', 86400)),
        ]);

        $this->events->dispatch(new CheckoutStarted($session));

        return $session;
    }

    public function resumeCheckout(string $sessionId): CheckoutSession
    {
        $session = CheckoutSession::find($sessionId);

        if ($session === null) {
            throw InvalidCheckoutStateException::sessionNotFound($sessionId);
        }

        if ($session->isExpired()) {
            throw InvalidCheckoutStateException::sessionExpired($sessionId);
        }

        return $session;
    }

    public function processCheckout(CheckoutSession $session): CheckoutResult
    {
        if ($session->status->isTerminal()) {
            throw InvalidCheckoutStateException::cannotModify($session->id, $session->status->name());
        }

        return $this->withSessionOwnerContext($session, function () use ($session): CheckoutResult {
            $this->transformSessionData($session);

            if (! $session->status->is(Processing::class)) {
                $session->transitionStatus(Processing::class);
            }

            try {
                return DB::transaction(function () use ($session) {
                    $this->ensureProcessingStatus($session);

                    $pipelineResult = $this->stepExecutor->run($session);

                    if ($pipelineResult->requiresRedirect() || ! $pipelineResult->success) {
                        return $pipelineResult;
                    }

                    return $this->finalizer->finalize($session);
                });
            } catch (Throwable $e) {
                $this->handleCheckoutFailure($session, $e);

                throw $e;
            }
        });
    }

    public function retryPayment(CheckoutSession $session): CheckoutResult
    {
        if (! $session->status->canRetryPayment()) {
            throw InvalidCheckoutStateException::cannotModify($session->id, $session->status->name());
        }

        $retryLimit = config('checkout.payment.retry_limit', 3);
        if ($session->payment_attempts >= $retryLimit) {
            throw PaymentException::retryLimitExceeded($session->payment_attempts, $retryLimit);
        }

        return $this->withSessionOwnerContext($session, function () use ($session): CheckoutResult {
            $session->setStepState('process_payment', StepStatus::Pending);
            $session->update([
                'payment_redirect_url' => null,
                'error_message' => null,
            ]);
            $session->transitionStatus(Processing::class);

            $paymentStep = $this->stepRegistry->get('process_payment');
            if ($paymentStep === null) {
                throw CheckoutStepException::stepNotFound('process_payment');
            }

            $result = $this->stepExecutor->processStep($session, $paymentStep);

            if ($session->payment_redirect_url !== null) {
                return CheckoutResult::awaitingPayment($session, $session->payment_redirect_url);
            }

            if ($result->isSuccessful()) {
                return $this->continueFromStep($session, 'process_payment');
            }

            return CheckoutResult::failed($session, $result->message ?? 'Payment failed', $result->errors);
        });
    }

    public function cancelCheckout(CheckoutSession $session): CheckoutSession
    {
        if (! $session->status->canCancel()) {
            throw InvalidCheckoutStateException::cannotCancel($session->id, $session->status->name());
        }

        return $this->withSessionOwnerContext($session, function () use ($session): CheckoutSession {
            $this->rollbackCompletedSteps($session);

            $session->transitionStatus(Cancelled::class);

            $this->events->dispatch(new CheckoutCancelled($session));

            return $session->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handlePaymentCallback(
        CheckoutSession $session,
        string $callbackType,
        array $payload = [],
    ): CheckoutResult {
        return $this->withSessionOwnerContext($session, function () use ($session, $callbackType, $payload): CheckoutResult {
            if ($callbackType === 'cancel') {
                if ($session->status->canCancel()) {
                    $this->rollbackCompletedSteps($session);
                    $session->transitionStatus(Cancelled::class);
                    $this->events->dispatch(new CheckoutCancelled($session));
                }

                return CheckoutResult::failed($session, 'Payment was cancelled');
            }

            if ($callbackType === 'failure') {
                $this->rollbackCompletedSteps($session);
                $session->transitionStatus(PaymentFailed::class);
                $session->update(['error_message' => 'Payment failed at gateway']);
                $this->events->dispatch(new CheckoutFailed($session, 'Payment failed'));

                return CheckoutResult::failed($session, 'Payment failed');
            }

            if ($callbackType === 'success') {
                return $this->verifyAndCompletePayment($session, $payload);
            }

            return CheckoutResult::failed($session, 'Unknown callback type');
        });
    }

    private function resolveCart(string $cartId): mixed
    {
        if (! app()->bound(CartManagerInterface::class)) {
            return null;
        }

        return app(CartManagerInterface::class)->getById($cartId);
    }

    /**
     * @return array<string, mixed>
     */
    private function createCartSnapshot(mixed $cart): array
    {
        $metadata = method_exists($cart, 'getAllMetadata') ? $cart->getAllMetadata() : [];
        $conditions = method_exists($cart, 'getConditions') ? $cart->getConditions()->toArray() : [];

        $subtotal = $cart->subtotal()->getAmount();
        $total = $cart->total()->getAmount();

        return [
            'items' => $cart->getItems()->toArray(),
            'metadata' => $metadata,
            'conditions' => $conditions,
            'totals' => [
                'subtotal' => $subtotal,
                'total' => $total,
            ],
            'subtotal' => $subtotal,
            'total' => $total,
            'item_count' => $cart->countItems(),
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function initializeStepStates(): array
    {
        $states = [];

        foreach ($this->stepRegistry->getEnabledStepIdentifiers() as $identifier) {
            $states[$identifier] = StepStatus::Pending->value;
        }

        return $states;
    }

    private function getFirstStepIdentifier(): ?string
    {
        $steps = $this->stepRegistry->getOrderedSteps();

        return ! empty($steps) ? $steps[0]->getIdentifier() : null;
    }

    private function ensureProcessingStatus(CheckoutSession $session): void
    {
        if (! $session->status->is(Processing::class)) {
            $session->transitionStatus(Processing::class);
        }
    }

    private function continueFromStep(CheckoutSession $session, string $fromStep): CheckoutResult
    {
        $pipelineResult = $this->stepExecutor->run($session, fromStep: $fromStep);

        if ($pipelineResult->requiresRedirect() || ! $pipelineResult->success) {
            return $pipelineResult;
        }

        return $this->finalizer->finalize($session);
    }

    private function rollbackCompletedSteps(CheckoutSession $session): void
    {
        $steps = array_reverse($this->stepRegistry->getOrderedSteps());

        foreach ($steps as $step) {
            $state = $session->getStepState($step->getIdentifier());

            if ($state === StepStatus::Completed) {
                $step->rollback($session);
                $session->setStepState($step->getIdentifier(), StepStatus::RolledBack);
            }
        }
    }

    private function handleCheckoutFailure(CheckoutSession $session, Throwable $e): void
    {
        $this->rollbackCompletedSteps($session);

        if (! $session->status->isTerminal() && ! $session->status->is(PaymentFailed::class)) {
            $session->transitionStatus(PaymentFailed::class);
        }

        Log::error('Checkout failure', [
            'session_id' => $session->getKey(),
            'exception' => $e,
        ]);

        $session->update(['error_message' => 'An error occurred during checkout. Please try again.']);

        $this->events->dispatch(new CheckoutFailed($session, $e->getMessage()));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function verifyAndCompletePayment(CheckoutSession $session, array $payload): CheckoutResult
    {
        $paymentVerified = false;
        $paymentResult = null;

        if (! empty($payload) && $this->paymentResolver !== null) {
            $gateway = $session->selected_payment_gateway;
            $processor = $this->paymentResolver->resolve($gateway);
            $paymentResult = $processor->handleCallback($payload);
        } elseif (! empty($payload)) {
            $status = $payload['status'] ?? $payload['data']['object']['status'] ?? null;

            $paymentResult = match ($status) {
                'paid', 'completed', 'succeeded', 'complete' => PaymentResult::success($session->payment_id ?? 'unknown'),
                'failed', 'error', 'payment_failed' => PaymentResult::failed('Payment failed', paymentId: $session->payment_id),
                'cancelled', 'canceled', 'expired' => new PaymentResult(
                    status: PaymentStatus::Cancelled,
                    paymentId: $session->payment_id,
                    gatewayResponse: $payload,
                ),
                default => new PaymentResult(
                    status: PaymentStatus::Processing,
                    paymentId: $session->payment_id,
                    gatewayResponse: $payload,
                ),
            };
        } elseif ($this->paymentResolver !== null && $session->payment_id !== null) {
            $gateway = $session->selected_payment_gateway;
            $processor = $this->paymentResolver->resolve($gateway);
            $paymentResult = $processor->checkStatus($session->payment_id);
        }

        if ($paymentResult !== null) {
            $paymentVerified = $paymentResult->status === PaymentStatus::Completed;

            $session->update([
                'payment_id' => $paymentResult->paymentId ?? $session->payment_id,
                'payment_data' => array_merge($session->payment_data ?? [], [
                    'status' => $paymentResult->status->value,
                    'verification_status' => $paymentResult->status->value,
                    'verified_at' => now()->toIso8601String(),
                    'payment_id' => $paymentResult->paymentId ?? $session->payment_id,
                    'transaction_id' => $paymentResult->transactionId ?? ($session->payment_data['transaction_id'] ?? null),
                    'amount' => $paymentResult->amount ?? ($session->payment_data['amount'] ?? null),
                    'currency' => $paymentResult->currency ?? ($session->payment_data['currency'] ?? $session->currency),
                    'gateway_response' => $paymentResult->gatewayResponse !== []
                        ? $paymentResult->gatewayResponse
                        : ($session->payment_data['gateway_response'] ?? null),
                ]),
            ]);
        }

        if (! $paymentVerified) {
            return CheckoutResult::failed($session, 'Payment could not be verified');
        }

        $this->dispatchPaymentCompleted($session);

        $session->setStepState('process_payment', StepStatus::Completed);
        $session->transitionStatus(Processing::class);
        $session->update(['payment_redirect_url' => null]);

        return $this->continueFromStep($session, 'process_payment');
    }

    private function transformSessionData(CheckoutSession $session): void
    {
        $billingData = $this->transformData('billing', $session->billing_data ?? [], $session);
        $shippingData = $this->transformData('shipping', $session->shipping_data ?? [], $session);

        $updates = [];

        if ($billingData !== ($session->billing_data ?? [])) {
            $updates['billing_data'] = $billingData;
        }

        if ($shippingData !== ($session->shipping_data ?? [])) {
            $updates['shipping_data'] = $shippingData;
        }

        if ($updates !== []) {
            $session->update($updates);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function transformData(string $type, array $data, CheckoutSession $session): array
    {
        $transformerClass = config("checkout.transformers.{$type}", NullSessionDataTransformer::class);

        $transformer = app($transformerClass);

        if (! $transformer instanceof SessionDataTransformerInterface) {
            throw new RuntimeException("Checkout {$type} transformer must implement " . SessionDataTransformerInterface::class);
        }

        return $transformer->transform($data, $session);
    }

    private function dispatchPaymentCompleted(CheckoutSession $session): void
    {
        $paymentData = $session->payment_data ?? [];

        $this->events->dispatch(new CheckoutPaymentCompleted(
            session: $session,
            paymentData: is_array($paymentData) ? $paymentData : [],
        ));
    }

    private function withSessionOwnerContext(CheckoutSession $session, callable $callback): mixed
    {
        /** @var Model|null $owner */
        $owner = $session->hasOwner() ? $session->owner : null;

        return OwnerContext::withOwner($owner, $callback);
    }
}
