<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Services;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Actions\CheckoutFinalizer;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Contracts\PaymentCompensationInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Contracts\ProviderAwarePaymentProcessorInterface;
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
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Transformers\NullSessionDataTransformer;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class CheckoutService implements CheckoutServiceInterface
{
    public function __construct(
        private readonly CheckoutStepRegistryInterface $stepRegistry,
        private readonly Dispatcher $events,
        private readonly StepExecutor $stepExecutor,
        private readonly CheckoutFinalizer $finalizer,
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

        $this->assertCustomerInScope($customerId);

        $session = CheckoutSession::forceCreate([
            'cart_id' => $cartId,
            'customer_id' => $customerId,
            'status' => Pending::class,
            'cart_snapshot' => $this->createCartSnapshot($cart),
            'step_states' => $this->initializeStepStates(),
            'current_step' => $this->getFirstStepIdentifier(),
            'expires_at' => CarbonImmutable::now()->addSeconds(config('checkout.defaults.session_ttl', 86400)),
        ]);

        $this->events->dispatch(new CheckoutStarted($session));

        return $session;
    }

    public function resumeCheckout(string $sessionId): CheckoutSession
    {
        // Session ids are UUID primary keys. Reject malformed ids before
        // querying so garbage input never reaches the database. Owner
        // isolation is enforced by the OwnerScope global scope, which fails
        // closed without a resolved or explicit-global owner context.
        if (! Str::isUuid($sessionId)) {
            throw InvalidCheckoutStateException::sessionNotFound($sessionId);
        }

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
                if ($this->paymentStepWillExecute($session)) {
                    return $this->processWithSplitTransactions($session);
                }

                $pipelineResult = DB::transaction(function () use ($session): CheckoutResult {
                    $this->lockAndRefreshSession($session);
                    $this->ensureProcessingStatus($session);

                    $pipelineResult = $this->stepExecutor->run($session);

                    if ($pipelineResult->requiresRedirect() || ! $pipelineResult->success) {
                        return $pipelineResult;
                    }

                    return $this->finalizer->finalize($session);
                });

                if (! $pipelineResult->requiresRedirect() && ! $pipelineResult->success) {
                    return $this->handleCheckoutFailureResult($session, $pipelineResult);
                }

                return $pipelineResult;
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
            // Reset under a row lock so a concurrent callback cannot interleave
            // between the guard checks and the reset. The payment step itself
            // runs outside the transaction because it performs gateway I/O.
            DB::transaction(function () use ($session): void {
                $this->lockAndRefreshSession($session);

                if (! $session->status->canRetryPayment()) {
                    throw InvalidCheckoutStateException::cannotModify($session->id, $session->status->name());
                }

                $retryLimit = config('checkout.payment.retry_limit', 3);

                if ($session->payment_attempts >= $retryLimit) {
                    throw PaymentException::retryLimitExceeded($session->payment_attempts, $retryLimit);
                }

                $session->setStepState('process_payment', StepStatus::Pending);
                $session->persistState([
                    'payment_redirect_url' => null,
                    'error_message' => null,
                ]);
                $session->transitionStatus(Processing::class);
            });

            $paymentStep = $this->stepRegistry->get('process_payment');
            if ($paymentStep === null) {
                throw CheckoutStepException::stepNotFound('process_payment');
            }

            try {
                $result = $this->stepExecutor->processStep($session, $paymentStep);
            } catch (Throwable $e) {
                $this->stepExecutor->compensate($session);
                $this->handleCheckoutFailure($session, $e);

                throw $e;
            }

            if ($session->payment_redirect_url !== null) {
                return CheckoutResult::awaitingPayment($session, $session->payment_redirect_url);
            }

            if ($result->isSuccessful()) {
                return $this->continueFromStep($session, 'process_payment');
            }

            $this->stepExecutor->compensate($session);

            return $this->handleCheckoutFailureResult(
                $session,
                CheckoutResult::failed($session, $result->message ?? 'Payment failed', $result->errors),
            );
        });
    }

    public function cancelCheckout(CheckoutSession $session): CheckoutSession
    {
        if (! $session->status->canCancel()) {
            throw InvalidCheckoutStateException::cannotCancel($session->id, $session->status->name());
        }

        return $this->withSessionOwnerContext($session, function () use ($session): CheckoutSession {
            // Record the cancellation under a row lock first so concurrent
            // cancels serialize: the loser sees Cancelled and throws instead
            // of double-compensating. Compensation itself runs outside the
            // transaction because it performs gateway I/O.
            DB::transaction(function () use ($session): void {
                $this->lockAndRefreshSession($session);

                if (! $session->status->canCancel()) {
                    throw InvalidCheckoutStateException::cannotCancel($session->id, $session->status->name());
                }

                $session->transitionStatus(Cancelled::class);
            });

            $this->stepExecutor->compensate($session);

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
                    $this->stepExecutor->compensate($session);
                    $session->transitionStatus(Cancelled::class);
                    $this->events->dispatch(new CheckoutCancelled($session));
                }

                return CheckoutResult::failed($session, 'Payment was cancelled');
            }

            if ($callbackType === 'failure') {
                // Gateway retries re-deliver failure callbacks. A repeat for an
                // already-failed session must not rewrite error state or
                // re-dispatch failure notifications. (The cancel branch below
                // is already idempotent via canCancel().)
                if ($session->status->is(PaymentFailed::class)) {
                    return CheckoutResult::failed(
                        $session,
                        $session->error_message ?? 'Payment failed at gateway',
                    );
                }

                $this->stepExecutor->compensate($session);

                return $this->handleCheckoutFailureResult(
                    $session,
                    CheckoutResult::failed($session, 'Payment failed at gateway'),
                );
            }

            if ($callbackType === 'success') {
                return $this->verifyAndCompletePayment($session, $payload);
            }

            return $this->handleCheckoutFailureResult(
                $session,
                CheckoutResult::failed($session, 'Unknown callback type'),
            );
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
     * Reject caller-supplied customer ids that do not exist or belong to a
     * different owner than the ambient context. Downstream steps load the
     * customer's saved addresses into the session, so an unchecked id is an
     * IDOR/PII leak. Hosts must additionally ensure the cart belongs to the
     * authenticated actor; cart ownership lives in the cart package.
     */
    private function assertCustomerInScope(?string $customerId): void
    {
        if ($customerId === null || $customerId === '') {
            return;
        }

        $customerModel = config('checkout.models.customer');

        if (! is_string($customerModel) || ! class_exists($customerModel) || ! is_subclass_of($customerModel, Model::class)) {
            return;
        }

        /** @var class-string<Model> $customerModel */
        $customer = (new $customerModel)->newQuery()->whereKey($customerId)->first();

        if (! $customer instanceof Model) {
            throw InvalidCheckoutStateException::customerNotFound($customerId);
        }

        $attributes = $customer->getAttributes();

        if (! array_key_exists('owner_type', $attributes) || ! array_key_exists('owner_id', $attributes)) {
            return;
        }

        $customerOwnerType = $customer->getAttribute('owner_type');
        $customerOwnerId = $customer->getAttribute('owner_id');

        if ($customerOwnerType === null || $customerOwnerId === null) {
            return;
        }

        $owner = OwnerContext::resolve();

        if (! $owner instanceof Model) {
            return;
        }

        if ($customerOwnerType !== $owner->getMorphClass() || (string) $customerOwnerId !== (string) $owner->getKey()) {
            throw InvalidCheckoutStateException::customerNotFound($customerId);
        }
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
            'captured_at' => CarbonImmutable::now()->toIso8601String(),
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

    /**
     * Run the pipeline with the payment step outside any transaction so
     * gateway HTTP never holds a database transaction open. Pre-payment
     * steps run in one transaction, the payment step runs lock-free, and
     * post-payment steps plus finalization run in a second transaction
     * after re-locking the row and checking for concurrent interference.
     */
    private function processWithSplitTransactions(CheckoutSession $session): CheckoutResult
    {
        $paymentStep = $this->stepRegistry->get('process_payment');

        if ($paymentStep === null) {
            throw CheckoutStepException::stepNotFound('process_payment');
        }

        $phaseResult = DB::transaction(function () use ($session): CheckoutResult {
            $this->lockAndRefreshSession($session);
            $this->ensureProcessingStatus($session);

            return $this->stepExecutor->run($session, untilStep: 'process_payment');
        });

        if ($phaseResult->requiresRedirect()) {
            return $phaseResult;
        }

        if (! $phaseResult->success) {
            return $this->handleCheckoutFailureResult($session, $phaseResult);
        }

        $stepResult = $this->stepExecutor->processStep($session, $paymentStep);

        if ($session->payment_redirect_url !== null) {
            return CheckoutResult::awaitingPayment($session, $session->payment_redirect_url);
        }

        if (! $stepResult->isSuccessful()) {
            $this->stepExecutor->compensate($session);

            return $this->handleCheckoutFailureResult(
                $session,
                CheckoutResult::failed($session, $stepResult->message ?? 'Payment failed', $stepResult->errors),
            );
        }

        $ourPaymentId = $session->payment_id;

        $completion = DB::transaction(function () use ($session, $ourPaymentId): CheckoutResult {
            $this->lockAndRefreshSession($session);

            if (! $session->status->is(Processing::class)) {
                return $this->quietInterruptionResult($session, $ourPaymentId);
            }

            return $this->continueFromStep($session, 'process_payment');
        });

        if (isset($completion->errors['interrupted'])) {
            return $completion;
        }

        if (! $completion->requiresRedirect() && ! $completion->success) {
            return $this->handleCheckoutFailureResult($session, $completion);
        }

        return $completion;
    }

    private function paymentStepWillExecute(CheckoutSession $session): bool
    {
        if (! $this->stepRegistry->isEnabled('process_payment')) {
            return false;
        }

        $paymentStep = $this->stepRegistry->get('process_payment');

        if ($paymentStep === null) {
            return false;
        }

        $state = $session->getStepState('process_payment');

        if ($state === StepStatus::Completed || $state === StepStatus::Skipped) {
            return false;
        }

        return ! $paymentStep->canSkip($session);
    }

    /**
     * Lock the session row and re-read it so concurrent pipeline runs and
     * callback flows serialize instead of lost-updating the JSON blobs.
     */
    private function lockAndRefreshSession(CheckoutSession $session): void
    {
        $locked = CheckoutSession::whereKey($session->getKey())->lockForUpdate()->first();

        if ($locked === null) {
            throw InvalidCheckoutStateException::sessionNotFound((string) $session->getKey());
        }

        // Owner tuples are immutable after creation, so re-reading the same
        // locked PK cannot cross owners.
        $session->refresh();
    }

    /**
     * Handle a session that a concurrent callback completed, failed, or
     * cancelled while this run's payment call was in flight. The winning
     * flow owns transitions and notifications, so this run stays quiet apart
     * from voiding a payment this run orphaned.
     */
    private function quietInterruptionResult(CheckoutSession $session, ?string $ourPaymentId): CheckoutResult
    {
        Log::warning('Checkout pipeline interrupted by a concurrent update; post-payment steps skipped', [
            'session_id' => $session->getKey(),
            'status' => $session->status->name(),
        ]);

        if (is_string($ourPaymentId) && $ourPaymentId !== '' && $session->payment_id !== $ourPaymentId) {
            $this->bestEffortVoidOrphanedPayment($session, $ourPaymentId);
        }

        if ($session->status->is(Completed::class)) {
            return CheckoutResult::success($session);
        }

        return CheckoutResult::failed(
            $session,
            'Checkout was updated by another process before completion',
            ['interrupted' => 'A concurrent callback completed, failed, or cancelled this checkout while payment was in flight.'],
        );
    }

    private function bestEffortVoidOrphanedPayment(CheckoutSession $session, string $paymentId): void
    {
        if ($this->paymentResolver === null) {
            Log::warning('Checkout cannot void an orphaned payment without a payment resolver', [
                'session_id' => $session->getKey(),
                'payment_id' => $paymentId,
            ]);

            return;
        }

        try {
            $processor = $this->paymentResolver->resolve($session->selected_payment_gateway);

            if (! $processor instanceof PaymentCompensationInterface) {
                Log::warning('Checkout cannot void an orphaned payment through a non-compensating processor', [
                    'session_id' => $session->getKey(),
                    'payment_id' => $paymentId,
                ]);

                return;
            }

            $processor->voidPayment($paymentId, 'Orphaned by a concurrent checkout update');
        } catch (Throwable $e) {
            Log::warning('Checkout failed to void an orphaned payment', [
                'session_id' => $session->getKey(),
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function continueFromStep(CheckoutSession $session, string $fromStep): CheckoutResult
    {
        $pipelineResult = $this->stepExecutor->run($session, fromStep: $fromStep);

        if ($pipelineResult->requiresRedirect()) {
            return $pipelineResult;
        }

        if (! $pipelineResult->success) {
            return $this->handleCheckoutFailureResult($session, $pipelineResult);
        }

        return $this->finalizer->finalize($session);
    }

    private function handleCheckoutFailure(CheckoutSession $session, Throwable $e): void
    {
        $this->stepExecutor->compensate($session);

        if (! $session->status->isTerminal() && ! $session->status->is(PaymentFailed::class)) {
            $session->transitionStatus(PaymentFailed::class);
        }

        Log::error('Checkout failure', [
            'session_id' => $session->getKey(),
            'exception' => $e,
        ]);

        $session->persistState(['error_message' => 'An error occurred during checkout. Please try again.']);

        $this->events->dispatch(new CheckoutFailed($session, $e->getMessage()));
    }

    private function handleCheckoutFailureResult(
        CheckoutSession $session,
        CheckoutResult $result,
    ): CheckoutResult {
        if (! $session->status->isTerminal() && ! $session->status->is(PaymentFailed::class)) {
            $session->transitionStatus(PaymentFailed::class);
        }

        $message = $result->message ?? 'Checkout failed';
        $session->persistState(['error_message' => $message]);
        $this->events->dispatch(new CheckoutFailed($session, $message));

        return CheckoutResult::failed($session, $message, $result->errors);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function verifyAndCompletePayment(CheckoutSession $session, array $payload): CheckoutResult
    {
        $paymentVerified = false;
        $hasPaymentEvidence = false;
        $paymentResult = null;

        if (! empty($payload) && $this->paymentResolver !== null) {
            $gateway = $session->selected_payment_gateway;
            $processor = $this->paymentResolver->resolve($gateway);
            $paymentResult = $processor->handleCallback($payload);
        } elseif (! empty($payload)) {
            $status = $payload['status'] ?? data_get($payload, 'data.object.status');
            $status = is_string($status) ? mb_strtolower(mb_trim($status)) : null;

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
            $paymentResult = $this->checkPaymentStatus($processor, $session);
        }

        if ($paymentResult !== null) {
            $paymentVerified = $paymentResult->status === PaymentStatus::Completed;
            $hasPaymentEvidence = $this->hasPaymentEvidence($paymentResult);
            $verificationStatus = $paymentVerified && ! $hasPaymentEvidence
                ? PaymentStatus::Failed
                : $paymentResult->status;

            if ($paymentVerified && ! $hasPaymentEvidence) {
                Log::warning('Checkout payment verification failed because gateway evidence was incomplete', [
                    'session_id' => $session->id,
                    'payment_id' => $paymentResult->paymentId,
                    'amount_present' => $paymentResult->amount !== null,
                    'currency_present' => $paymentResult->currency !== null,
                ]);
            }

            $paymentData = array_merge($session->payment_data ?? [], [
                'status' => $verificationStatus->value,
                'verification_status' => $verificationStatus->value,
                'verified_at' => CarbonImmutable::now()->toIso8601String(),
                'payment_id' => $paymentResult->paymentId ?? $session->payment_id,
                'transaction_id' => $paymentResult->transactionId ?? ($session->payment_data['transaction_id'] ?? null),
                'provider' => $paymentResult->provider ?? ($session->payment_data['provider'] ?? null),
                'gateway_response' => $paymentResult->gatewayResponse !== []
                    ? $paymentResult->gatewayResponse
                    : ($session->payment_data['gateway_response'] ?? null),
            ]);

            if ($paymentResult->amount !== null) {
                $paymentData['amount'] = $paymentResult->amount;
            }

            if ($paymentResult->currency !== null) {
                $paymentData['currency'] = $paymentResult->currency;
            }

            $session->persistState([
                'payment_id' => $paymentResult->paymentId ?? $session->payment_id,
                'payment_data' => $paymentData,
            ]);
        }

        if (! $paymentVerified || ! $hasPaymentEvidence) {
            $this->recordVerificationFailure($session);

            return CheckoutResult::failed($session, 'Payment could not be verified');
        }

        // Atomicity comes from HandleCheckoutPaymentCallback's transaction,
        // which already holds the session row lock; a nested transaction here
        // would only add a savepoint without changing rollback semantics.
        $this->dispatchPaymentCompleted($session);

        $session->setStepState('process_payment', StepStatus::Completed);
        $session->transitionStatus(Processing::class);
        $session->persistState(['payment_redirect_url' => null]);

        return $this->continueFromStep($session, 'process_payment');
    }

    /**
     * Record an unverifiable payment so the failure is observable. The
     * session deliberately stays in its retryable state: no transition and
     * no failure event, just persisted evidence plus a structured log.
     */
    private function recordVerificationFailure(CheckoutSession $session): void
    {
        $paymentData = $session->payment_data ?? [];
        $paymentData['verification_status'] = PaymentStatus::Failed->value;
        $paymentData['verified_at'] = CarbonImmutable::now()->toIso8601String();

        $session->persistState([
            'payment_data' => $paymentData,
            'error_message' => 'Payment could not be verified',
        ]);

        Log::warning('Checkout payment could not be verified', [
            'session_id' => $session->getKey(),
            'payment_id' => $session->payment_id,
            'gateway' => $session->selected_payment_gateway,
        ]);
    }

    private function hasPaymentEvidence(PaymentResult $paymentResult): bool
    {
        if ($paymentResult->amount === null || $paymentResult->amount < 0) {
            return false;
        }

        if (! is_string($paymentResult->currency)) {
            return false;
        }

        return preg_match('/^[A-Z]{3}$/', mb_strtoupper(mb_trim($paymentResult->currency))) === 1;
    }

    private function checkPaymentStatus(
        PaymentProcessorInterface $processor,
        CheckoutSession $session,
    ): PaymentResult {
        $provider = data_get($session->payment_data ?? [], 'provider');

        if (
            is_string($provider)
            && $provider !== ''
            && $processor instanceof ProviderAwarePaymentProcessorInterface
            && $provider !== $processor->getIdentifier()
        ) {
            return $processor->checkStatusForProvider($provider, $session->payment_id ?? '');
        }

        return $processor->checkStatus($session->payment_id ?? '');
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
            $session->persistState($updates);
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
