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
use AIArmada\Checkout\Data\PaymentFailureOrigin;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentAttemptOutcome;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Events\CheckoutCancelled;
use AIArmada\Checkout\Events\CheckoutFailed;
use AIArmada\Checkout\Events\CheckoutPaymentCompleted;
use AIArmada\Checkout\Events\CheckoutStarted;
use AIArmada\Checkout\Exceptions\CheckoutStepException;
use AIArmada\Checkout\Exceptions\InvalidCheckoutStateException;
use AIArmada\Checkout\Exceptions\PaymentAttemptExecutionException;
use AIArmada\Checkout\Exceptions\PaymentException;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Cancelled;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Support\CheckoutProcessGuard;
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
        private readonly ?PaymentAttemptManager $attemptManager = null,
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

    private function processGuard(): CheckoutProcessGuard
    {
        return app(CheckoutProcessGuard::class);
    }

    private function attemptManager(): PaymentAttemptManager
    {
        return $this->attemptManager ?? app(PaymentAttemptManager::class);
    }

    public function processCheckout(CheckoutSession $session): CheckoutResult
    {
        $sessionId = (string) $session->getKey();
        $this->processGuard()->mark($sessionId);

        try {
            return $this->withSessionOwnerContext($session, function () use ($session): CheckoutResult {
                // Decide from the authoritative locked row before any transformer
                // run or state mutation: a stale instance must never reopen a
                // committed terminal session or complete an order for a redirect
                // that is already awaiting its callback.
                $verdict = DB::transaction(function () use ($session): string {
                    $this->lockAndRefreshSession($session);

                    if ($session->status->is(Completed::class)) {
                        return 'completed';
                    }

                    if ($session->status->isTerminal()) {
                        return 'rejected';
                    }

                    if ($session->status->is(AwaitingPayment::class) && $session->payment_redirect_url !== null) {
                        return 'redirect';
                    }

                    $this->transformSessionData($session);
                    $this->ensureProcessingStatus($session);

                    return 'process';
                });

                if ($verdict === 'completed') {
                    return CheckoutResult::success($session);
                }

                if ($verdict === 'redirect') {
                    return CheckoutResult::awaitingPayment($session, (string) $session->payment_redirect_url);
                }

                if ($verdict === 'rejected') {
                    throw InvalidCheckoutStateException::cannotModify($session->id, $session->status->name());
                }

                try {
                    $executionOrigin = $this->attemptManager()->captureFailureOrigin($session);

                    if ($this->paymentStepWillExecute($session, $executionOrigin)) {
                        return $this->processWithSplitTransactions($session, $executionOrigin);
                    }

                    $pipelineResult = DB::transaction(function () use ($session, $executionOrigin): CheckoutResult {
                        $this->lockAndRefreshSession($session);
                        $this->ensureProcessingStatus($session);

                        $pipelineResult = $this->stepExecutor->run($session, origin: $executionOrigin);

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
                    $observation = $this->handleCheckoutFailure($session, $e);

                    if ($observation !== null) {
                        return $observation;
                    }

                    throw $this->originalException($e);
                }
            });
        } finally {
            $this->processGuard()->unmark($sessionId);
        }
    }

    public function retryPayment(CheckoutSession $session): CheckoutResult
    {
        return $this->recoverPayment($session);
    }

    public function recoverPayment(CheckoutSession $session): CheckoutResult
    {
        if ($this->processGuard()->isActive((string) $session->getKey())) {
            throw InvalidCheckoutStateException::cannotModify($session->id, $session->status->name());
        }

        return $this->withSessionOwnerContext($session, function () use ($session): CheckoutResult {
            // Decide from the locked authoritative attempt classification so
            // recovery eligibility and the claim agree on which generation
            // owns the outcome. The payment step itself runs outside the
            // transaction because it performs gateway I/O.
            $executionOrigin = DB::transaction(function () use ($session): ?PaymentFailureOrigin {
                $this->lockAndRefreshSession($session);

                $isProcessing = $session->status->is(PaymentProcessing::class);
                $isPaymentFailed = $session->status->is(PaymentFailed::class);

                if (! $session->status->canRetryPayment() && ! $isProcessing && ! $isPaymentFailed) {
                    throw InvalidCheckoutStateException::cannotModify($session->id, $session->status->name());
                }

                $classification = $this->attemptManager()->outcomeClassification($session);
                $attempts = (int) $session->payment_attempts;
                $retryLimit = max(0, (int) config('checkout.payment.retry_limit', 3));

                if ($classification->requiresNewAttemptBudget() && $attempts >= $retryLimit) {
                    throw PaymentException::retryLimitExceeded($attempts, $retryLimit);
                }

                if ($classification === PaymentAttemptOutcome::Recorded) {
                    // The recorded outcome is authoritative and still in
                    // flight: recovery may observe it but must not re-drive
                    // the gateway or release a new generation.
                    return null;
                }

                // Mark exactly which attempt generation this explicit retry is
                // allowed to reconcile, so the claim can adopt a crashed or
                // uncertain attempt's frozen identity instead of abandoning it.
                $paymentData = $session->payment_data ?? [];
                $paymentData['retry_requested_for_attempt'] = $attempts;

                $session->setStepState('process_payment', StepStatus::Pending);
                $session->persistState([
                    'payment_redirect_url' => null,
                    'error_message' => null,
                    'payment_data' => $paymentData,
                ]);
                $session->transitionStatus(Processing::class);

                return $this->attemptManager()->captureFailureOrigin($session);
            });

            if ($executionOrigin === null) {
                return $this->resolveObservedPaymentOutcome($session);
            }

            $paymentStep = $this->stepRegistry->get('process_payment');
            if ($paymentStep === null) {
                throw CheckoutStepException::stepNotFound('process_payment');
            }

            try {
                $result = $this->stepExecutor->processStep($session, $paymentStep, $executionOrigin);
            } catch (Throwable $e) {
                $observation = $this->handleCheckoutFailure($session, $e);

                if ($observation !== null) {
                    return $observation;
                }

                throw $this->originalException($e);
            }

            if ($result->isObserved()) {
                return $this->resolveObservedPaymentOutcome($session);
            }

            if ($session->payment_redirect_url !== null) {
                return CheckoutResult::awaitingPayment($session, $session->payment_redirect_url);
            }

            if ($result->isSuccessful()) {
                return $this->continueFromStep($session, 'process_payment');
            }

            return $this->handleCheckoutFailureResult(
                $session,
                CheckoutResult::failed(
                    $session,
                    $result->message ?? 'Payment failed',
                    $result->errors,
                    failureOrigin: $result->failureOrigin,
                ),
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
            $handle = function () use ($session, $callbackType, $payload): CheckoutResult {
                $this->lockAndRefreshSession($session);

                if ($callbackType === 'cancel') {
                    if ($session->status->is(Completed::class)) {
                        return CheckoutResult::success($session);
                    }

                    // Fence: the callback must belong to the current attempt
                    // generation, not merely to this checkout session.
                    $ownership = $this->attemptManager()->classifyCallbackOwnership(
                        $session,
                        $this->callbackAttemptEvidence($payload),
                        $this->callbackGatewayEvidence($payload),
                        $this->callbackProviderEvidence($payload),
                        $this->extractPaymentIdFromCallbackPayload($payload),
                        allowMissingPaymentIdentity: true,
                    );

                    if (! $ownership->owned) {
                        return CheckoutResult::failed($session, $ownership->reason);
                    }

                    if ($session->status->canCancel()) {
                        $this->stepExecutor->compensate($session);
                        $session->transitionStatus(Cancelled::class);
                        $this->events->dispatch(new CheckoutCancelled($session));
                    }

                    return CheckoutResult::failed($session, 'Payment was cancelled');
                }

                if ($callbackType === 'failure') {
                    if ($session->status->is(Completed::class)) {
                        return CheckoutResult::success($session);
                    }

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

                    // Fence: bind the failure to the attempt that owns it. A
                    // failure is authoritative only for its own generation; an
                    // obsolete receipt must not fail the active attempt.
                    $payloadPaymentId = $this->extractPaymentIdFromCallbackPayload($payload);

                    $ownership = $this->attemptManager()->classifyCallbackOwnership(
                        $session,
                        $this->callbackAttemptEvidence($payload),
                        $this->callbackGatewayEvidence($payload),
                        $this->callbackProviderEvidence($payload),
                        $payloadPaymentId,
                        allowMissingPaymentIdentity: true,
                    );

                    if (! $ownership->owned) {
                        return CheckoutResult::failed($session, $ownership->reason);
                    }

                    // A verified failure is the attempt's durable outcome: record
                    // it against the owning generation so an explicit retry can
                    // release a replacement attempt instead of observing the
                    // stale pending outcome forever.
                    $origin = $this->attemptManager()->recordDefinitiveFailure($session, $payloadPaymentId);

                    return $this->handleCheckoutFailureResult(
                        $session,
                        CheckoutResult::failed(
                            $session,
                            'Payment failed at gateway',
                            failureOrigin: $origin,
                        ),
                    );
                }

                if ($callbackType === 'success') {
                    return $this->verifyAndCompletePayment($session, $payload);
                }

                return $this->handleCheckoutFailureResult(
                    $session,
                    CheckoutResult::failed(
                        $session,
                        'Unknown callback type',
                        failureOrigin: $this->attemptManager()->captureFailureOrigin($session),
                    ),
                );
            };

            // Join the action's transaction/lock when present. Direct callers
            // still get an atomic ownership decision and guarded writes.
            return DB::transactionLevel() > 0
                ? $handle()
                : DB::transaction($handle);
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
        // PaymentProcessing already means the pipeline is mid-payment; never
        // downgrade it to Processing, which would lose the in-flight attempt
        // and make an explicit recovery look like a fresh checkout.
        if ($session->status->is(Processing::class) || $session->status->is(PaymentProcessing::class)) {
            return;
        }

        $session->transitionStatus(Processing::class);
    }

    /**
     * Run the pipeline with the payment step outside any transaction so
     * gateway HTTP never holds a database transaction open. Pre-payment
     * steps run in one transaction, the payment step runs lock-free, and
     * post-payment steps plus finalization run in a second transaction
     * after re-locking the row and checking for concurrent interference.
     */
    private function processWithSplitTransactions(
        CheckoutSession $session,
        PaymentFailureOrigin $origin,
    ): CheckoutResult {
        $paymentStep = $this->stepRegistry->get('process_payment');

        if ($paymentStep === null) {
            throw CheckoutStepException::stepNotFound('process_payment');
        }

        $phaseResult = DB::transaction(function () use ($session, $origin): CheckoutResult {
            $this->lockAndRefreshSession($session);
            $this->ensureProcessingStatus($session);

            return $this->stepExecutor->run($session, untilStep: 'process_payment', origin: $origin);
        });

        if ($phaseResult->requiresRedirect()) {
            return $phaseResult;
        }

        if (! $phaseResult->success) {
            return $this->handleCheckoutFailureResult($session, $phaseResult);
        }

        $stepResult = $this->stepExecutor->processStep($session, $paymentStep, $origin);

        if ($stepResult->isObserved()) {
            return $this->resolveObservedPaymentOutcome($session);
        }

        if ($session->payment_redirect_url !== null) {
            return CheckoutResult::awaitingPayment($session, $session->payment_redirect_url);
        }

        if (! $stepResult->isSuccessful()) {
            return $this->handleCheckoutFailureResult(
                $session,
                CheckoutResult::failed(
                    $session,
                    $stepResult->message ?? 'Payment failed',
                    $stepResult->errors,
                    failureOrigin: $stepResult->failureOrigin,
                ),
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

    /**
     * Resolve the outcome when this run's payment step only observed an
     * attempt that another flow owns: replay a completed payment or an
     * existing redirect, report a reconciliation in progress when the
     * current generation's outcome is unknown or unrecorded, report a known
     * in-flight attempt as processing, and otherwise stay quiet instead of
     * compensating, reopening, or notifying.
     */
    private function resolveObservedPaymentOutcome(CheckoutSession $session): CheckoutResult
    {
        $classification = DB::transaction(function () use ($session): PaymentAttemptOutcome {
            $this->lockAndRefreshSession($session);

            return $this->attemptManager()->outcomeClassification($session);
        });

        if ($session->status->is(Completed::class)) {
            return CheckoutResult::success($session);
        }

        if ($session->status->is(AwaitingPayment::class) && $session->payment_redirect_url !== null) {
            return CheckoutResult::awaitingPayment($session, (string) $session->payment_redirect_url);
        }

        if ($classification === PaymentAttemptOutcome::Reconcile) {
            return CheckoutResult::reconciliation($session);
        }

        if ($session->status->is(Processing::class)
            || $session->status->is(PaymentProcessing::class)
            || $session->status->is(AwaitingPayment::class)
        ) {
            return CheckoutResult::processing($session);
        }

        return $this->quietInterruptionResult($session, null);
    }

    private function paymentStepWillExecute(
        CheckoutSession $session,
        PaymentFailureOrigin $origin,
    ): bool {
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

        try {
            return ! $paymentStep->canSkip($session);
        } catch (PaymentAttemptExecutionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw PaymentAttemptExecutionException::withOrigin($origin, $e);
        }
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
            $processor = $this->paymentResolver->resolve($this->currentAttemptGateway($session));

            if (! $processor instanceof PaymentCompensationInterface) {
                Log::warning('Checkout cannot void an orphaned payment through a non-compensating processor', [
                    'session_id' => $session->getKey(),
                    'payment_id' => $paymentId,
                ]);

                return;
            }

            $provider = $this->attemptManager()->frozenProvider($session);
            $reason = 'Orphaned by a concurrent checkout update';

            if (
                is_string($provider)
                && $provider !== ''
                && $processor instanceof ProviderAwarePaymentProcessorInterface
                && $provider !== $processor->getIdentifier()
            ) {
                $processor->voidPaymentForProvider($provider, $paymentId, $reason);

                return;
            }

            $processor->voidPayment($paymentId, $reason);
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

    private function handleCheckoutFailure(CheckoutSession $session, Throwable $e): ?CheckoutResult
    {
        $origin = $e instanceof PaymentAttemptExecutionException
            ? $e->origin
            : $this->attemptManager()->captureFailureOrigin($session);

        // Claim a durable operation under the row lock. It captures the exact
        // generation and resources this execution may compensate; a stale
        // execution gets no token and observes the current owner instead.
        $operation = $this->attemptManager()->beginFailureOperation($session, $origin);

        if ($operation === null) {
            return $this->resolveObservedPaymentOutcome($session);
        }

        $this->stepExecutor->compensate($session, $operation);

        if (! $this->attemptManager()->finishFailureOperationIfOwned(
            $session,
            $operation,
            'An error occurred during checkout. Please try again.',
        )) {
            return $this->resolveObservedPaymentOutcome($session);
        }

        Log::error('Checkout failure', [
            'session_id' => $session->getKey(),
            'exception' => $e,
        ]);

        $this->events->dispatch(new CheckoutFailed($session, $e->getMessage()));

        return null;
    }

    /**
     * Unwrap an attempt-fenced failure so callers of the public checkout API
     * receive the original exception semantics. The fence only exists between
     * the payment step and this service.
     */
    private function originalException(Throwable $e): Throwable
    {
        return $e instanceof PaymentAttemptExecutionException ? $e->getOriginal() : $e;
    }

    private function handleCheckoutFailureResult(
        CheckoutSession $session,
        CheckoutResult $result,
        ?PaymentFailureOrigin $origin = null,
    ): CheckoutResult {
        // Returned step failures, validation failures, and callback-driven
        // failures all claim the same operation token. The origin is the
        // exact state the failing execution observed, never a fresh read of
        // whichever generation happens to be current now. A newer generation
        // or an outcome another flow owns means this result is stale: observe
        // without compensating, failing, writing an error, or notifying.
        $operation = $this->attemptManager()->beginFailureOperation(
            $session,
            $origin ?? $result->failureOrigin ?? $this->attemptManager()->captureFailureOrigin($session),
        );

        if ($operation === null) {
            return $this->resolveObservedPaymentOutcome($session);
        }

        $this->stepExecutor->compensate($session, $operation);

        $message = $result->message ?? 'Checkout failed';

        if (! $this->attemptManager()->finishFailureOperationIfOwned($session, $operation, $message)) {
            return $this->resolveObservedPaymentOutcome($session);
        }

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
            $processor = $this->paymentResolver->resolve($this->currentAttemptGateway($session));
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
            $processor = $this->paymentResolver->resolve($this->currentAttemptGateway($session));
            $paymentResult = $this->checkPaymentStatus($processor, $session);
        }

        if ($paymentResult !== null) {
            // Fence: the provider result must belong to the current attempt
            // generation. A receipt for an older generation (or one whose
            // generation cannot be proven before the create response) must
            // never complete or mutate the active attempt.
            $ownership = $this->attemptManager()->classifyCallbackOwnership(
                $session,
                $this->callbackAttemptEvidence($payload),
                $this->callbackGatewayEvidence($payload),
                $paymentResult->provider ?? $this->callbackProviderEvidence($payload),
                $paymentResult->paymentId,
            );

            if (! $ownership->owned) {
                if ($ownership->superseded
                    && $paymentResult->status === PaymentStatus::Completed
                    && is_string($paymentResult->paymentId)
                    && $paymentResult->paymentId !== '') {
                    // A genuinely paid obsolete receipt is retained as
                    // attempt-specific reconciliation evidence. It must not
                    // replace the current payment or refund the winner.
                    $this->attemptManager()->recordObsoleteReceipt($session, [
                        'attempt' => $ownership->attempt,
                        'payment_id' => $paymentResult->paymentId,
                        'status' => $paymentResult->status->value,
                        'gateway' => $this->callbackGatewayEvidence($payload) ?? $this->currentAttemptGateway($session),
                        'provider' => $paymentResult->provider ?? $this->callbackProviderEvidence($payload),
                        'amount' => $paymentResult->amount,
                        'currency' => $paymentResult->currency,
                    ]);

                    Log::warning('Checkout retained a superseded paid receipt without granting it authority', [
                        'session_id' => $session->getKey(),
                        'receipt_payment_id' => $paymentResult->paymentId,
                        'current_payment_id' => data_get($session->payment_data ?? [], 'payment_attempt_outcome.payment_id'),
                        'receipt_attempt' => $ownership->attempt,
                        'current_attempt' => (int) $session->payment_attempts,
                    ]);
                }

                return CheckoutResult::failed($session, $ownership->reason);
            }

            // Fence: a different payment id must not silently replace a known
            // current payment id. Support callback-before-create-response when
            // no payment id is known yet.
            $currentPaymentId = $session->payment_id;
            $resultPaymentId = $paymentResult->paymentId;
            if (is_string($currentPaymentId) && $currentPaymentId !== ''
                && is_string($resultPaymentId) && $resultPaymentId !== ''
                && $currentPaymentId !== $resultPaymentId) {
                $this->recordVerificationFailure($session);

                return CheckoutResult::failed($session, 'Payment identity mismatch');
            }

            $paymentVerified = $paymentResult->status === PaymentStatus::Completed;
            $hasPaymentEvidence = $this->hasPaymentEvidence($paymentResult);

            if ($paymentVerified && $hasPaymentEvidence) {
                $this->attemptManager()->recordVerifiedOutcome($session, $paymentResult);
            }

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

    private function extractPaymentIdFromCallbackPayload(array $payload): ?string
    {
        $paymentId = data_get($payload, 'data.object.id')
            ?? $payload['id']
            ?? $payload['payment_id']
            ?? null;

        return is_string($paymentId) && mb_trim($paymentId) !== ''
            ? mb_trim($paymentId)
            : null;
    }

    /**
     * Merge the metadata locations used by the built-in providers. Stripe
     * payloads nest it under `data.object`, CHIP under `purchase`, while
     * direct browser/test callbacks commonly use the top level.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function callbackMetadata(array $payload): array
    {
        $metadata = [];

        foreach (['metadata', 'data.object.metadata', 'purchase.metadata'] as $path) {
            $candidate = data_get($payload, $path);

            if (is_array($candidate)) {
                $metadata = array_replace($metadata, $candidate);
            }
        }

        return $metadata;
    }

    /**
     * Provider generation evidence. The claim ID/frozen attempt is the only
     * trusted way to bind a callback that arrives before its create response.
     *
     * @param  array<string, mixed>  $payload
     */
    private function callbackAttemptEvidence(array $payload): ?int
    {
        $metadata = $this->callbackMetadata($payload);
        $attempt = $metadata['checkout_attempt'] ?? data_get($payload, 'checkout_attempt');

        if (is_int($attempt) || (is_string($attempt) && ctype_digit(mb_trim($attempt)))) {
            return (int) $attempt;
        }

        $idempotencyKey = $metadata['checkout_idempotency_key']
            ?? data_get($payload, 'checkout_idempotency_key')
            ?? $metadata['idempotency_key']
            ?? data_get($payload, 'idempotency_key');

        if (is_string($idempotencyKey) && preg_match('/attempt:(\d+)$/', $idempotencyKey, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function callbackGatewayEvidence(array $payload): ?string
    {
        $metadata = $this->callbackMetadata($payload);
        $gateway = $metadata['checkout_gateway'] ?? data_get($payload, 'gateway');

        return is_string($gateway) && mb_trim($gateway) !== '' ? mb_trim($gateway) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function callbackProviderEvidence(array $payload): ?string
    {
        $metadata = $this->callbackMetadata($payload);
        $provider = $metadata['checkout_provider'] ?? data_get($payload, 'provider');

        return is_string($provider) && mb_trim($provider) !== '' ? mb_trim($provider) : null;
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
        $provider = $this->attemptManager()->frozenProvider($session);

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

    /**
     * Gateway of the current attempt generation: the frozen claim gateway
     * when one exists, otherwise the mutable selection for sessions that
     * never claimed an attempt.
     */
    private function currentAttemptGateway(CheckoutSession $session): ?string
    {
        return $this->attemptManager()->currentAttemptGateway($session);
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
