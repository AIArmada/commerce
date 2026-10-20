<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Services;

use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Data\CheckoutResult;
use AIArmada\Checkout\Data\PaymentFailureOperation;
use AIArmada\Checkout\Data\PaymentFailureOrigin;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Events\CheckoutStepCompleted;
use AIArmada\Checkout\Events\CheckoutStepFailed;
use AIArmada\Checkout\Exceptions\CheckoutStepException;
use AIArmada\Checkout\Exceptions\PaymentAttemptExecutionException;
use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

final readonly class StepExecutor
{
    public function __construct(
        private CheckoutStepRegistryInterface $stepRegistry,
        private Dispatcher $events,
        private ?PaymentAttemptManager $attemptManager = null,
    ) {}

    public function run(
        CheckoutSession $session,
        ?string $fromStep = null,
        ?string $untilStep = null,
        ?PaymentFailureOrigin $origin = null,
    ): CheckoutResult {
        $startProcessing = $fromStep === null;
        $stoppedEarly = false;

        foreach ($this->stepRegistry->getOrderedSteps() as $step) {
            if (! $startProcessing && $step->getIdentifier() === $fromStep) {
                $startProcessing = true;

                continue;
            }

            if (! $startProcessing) {
                continue;
            }

            if ($untilStep !== null && $step->getIdentifier() === $untilStep) {
                $stoppedEarly = true;

                break;
            }

            $stepState = $session->getStepState($step->getIdentifier());

            if ($stepState === StepStatus::Completed || $stepState === StepStatus::Skipped) {
                continue;
            }

            $origin ??= $this->attemptManager()->captureFailureOrigin($session);

            try {
                $canSkip = $step->canSkip($session);
            } catch (PaymentAttemptExecutionException $e) {
                throw $e;
            } catch (Throwable $e) {
                throw PaymentAttemptExecutionException::withOrigin($origin, $e);
            }

            if ($canSkip) {
                if (! $this->attemptManager()->recordStepStateIfOwned(
                    $session,
                    $step->getIdentifier(),
                    StepStatus::Skipped,
                    $origin,
                )) {
                    return $this->observedCheckoutResult($session);
                }

                continue;
            }

            $result = $this->processStep($session, $step, $origin);

            if ($result->isObserved()) {
                return $this->observedCheckoutResult($session);
            }

            $origin = $result->executionOrigin ?? $origin;

            if (! $result->isSuccessful()) {
                return CheckoutResult::failed(
                    $session,
                    $result->message ?? 'Step failed',
                    $result->errors,
                    failureOrigin: $result->failureOrigin,
                );
            }

            if ($step->getIdentifier() === 'process_payment' && $session->payment_redirect_url !== null) {
                return CheckoutResult::awaitingPayment($session, $session->payment_redirect_url);
            }
        }

        return new CheckoutResult(
            success: true,
            status: $session->status,
            sessionId: $session->id,
            orderId: $session->order_id,
            paymentId: $session->payment_id,
            redirectUrl: $session->payment_redirect_url,
            message: $stoppedEarly ? "Pipeline paused before {$untilStep}" : 'Pipeline completed',
        );
    }

    public function processStep(
        CheckoutSession $session,
        CheckoutStepInterface $step,
        ?PaymentFailureOrigin $origin = null,
    ): StepResult {
        $identifier = $step->getIdentifier();
        $origin ??= $this->attemptManager()->captureFailureOrigin($session);

        try {
            foreach ($step->getDependencies() as $dependency) {
                $depState = $session->getStepState($dependency);
                if ($depState !== StepStatus::Completed && $depState !== StepStatus::Skipped) {
                    throw CheckoutStepException::dependencyNotMet($identifier, $dependency);
                }
            }
        } catch (PaymentAttemptExecutionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw PaymentAttemptExecutionException::withOrigin($origin, $e);
        }

        try {
            $errors = $step->validate($session);
        } catch (PaymentAttemptExecutionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw PaymentAttemptExecutionException::withOrigin($origin, $e);
        }
        if (! empty($errors)) {
            if (! $this->attemptManager()->recordStepFailureIfOwned($session, $identifier, $origin)) {
                return StepResult::observed($identifier, 'Payment result was superseded by a concurrent update', [
                    'payment_claim' => 'observed',
                ]);
            }

            $this->events->dispatch(new CheckoutStepFailed($session, $identifier, $errors));

            return StepResult::failed($identifier, 'Validation failed', $errors, $origin)
                ->withExecutionOrigin($origin);
        }

        // Guard the initial beginStep() write: an observing process must not
        // rewrite the winner's step map/current-step pointer before it
        // discovers it lacks ownership. The ownership read and the write
        // share the session row lock so a winner cannot arrive between them.
        if (! $this->attemptManager()->beginStepIfOwned($session, $identifier, $origin)) {
            return StepResult::observed($identifier, 'Payment result was superseded by a concurrent update', [
                'payment_claim' => 'observed',
            ]);
        }

        try {
            $result = $step->handle($session);
        } catch (Throwable $e) {
            return $this->recordThrownStep($session, $identifier, $e, $origin);
        }

        if ($result->isObserved()) {
            // Another flow owns this step's outcome; keep the previous state
            // and stay quiet so a concurrent observer cannot mark the step
            // completed or failed on the winner's behalf.
            return $result;
        }

        $stepOrigin = $result->executionOrigin ?? $result->failureOrigin ?? $origin;

        if (! $result->isSuccessful() && $result->failureOrigin === null) {
            $result = $result->withFailureOrigin($stepOrigin);
        }

        if ($result->executionOrigin === null) {
            $result = $result->withExecutionOrigin($stepOrigin);
        }

        if (! $this->attemptManager()->recordStepStateIfOwned(
            $session,
            $identifier,
            $result->status,
            $stepOrigin,
        )) {
            return StepResult::observed($identifier, 'Payment result was superseded by a concurrent update', [
                'payment_claim' => 'observed',
            ]);
        }

        if ($result->isSuccessful()) {
            $this->events->dispatch(new CheckoutStepCompleted($session, $identifier, $result->data));
        } else {
            $this->events->dispatch(new CheckoutStepFailed($session, $identifier, $result->errors));
        }

        return $result;
    }

    /**
     * Fence a thrown step failure by the execution's authoritative attempt.
     *
     * The ownership decision and the failed-step write run under the session
     * row lock. When the execution no longer owns the attempt — a newer
     * generation was claimed, a callback completed/redirected the session,
     * or another flow recorded a definitive outcome — the failure is routed
     * through the same observation outcome as a stale returned result:
     * nothing is written, no event is emitted, and nothing is thrown.
     *
     * A genuine current-attempt failure keeps its previous behavior: the
     * step is marked failed, the failure event is emitted, and the failure
     * (with its claimed attempt context) reaches the service failure handler.
     */
    private function recordThrownStep(
        CheckoutSession $session,
        string $stepIdentifier,
        Throwable $e,
        PaymentFailureOrigin $executionOrigin,
    ): StepResult {
        $origin = $executionOrigin;
        $thrown = $e;

        if ($e instanceof PaymentAttemptExecutionException) {
            $origin = $e->origin;
            $thrown = $e->getOriginal();
        }

        if (! $this->attemptManager()->recordStepFailureIfOwned($session, $stepIdentifier, $origin)) {
            return StepResult::observed($stepIdentifier, 'Payment result was superseded by a concurrent update', [
                'payment_claim' => 'observed',
            ]);
        }

        $message = $thrown->getMessage() !== '' ? $thrown->getMessage() : $thrown::class;
        $this->events->dispatch(new CheckoutStepFailed(
            $session,
            $stepIdentifier,
            ['exception' => $message],
        ));

        if ($e instanceof PaymentAttemptExecutionException) {
            throw $e;
        }

        throw PaymentAttemptExecutionException::withOrigin($origin, $thrown);
    }

    private function observedCheckoutResult(CheckoutSession $session): CheckoutResult
    {
        return new CheckoutResult(
            success: false,
            status: $session->status,
            sessionId: $session->id,
            orderId: $session->order_id,
            paymentId: $session->payment_id,
            redirectUrl: $session->payment_redirect_url,
            message: 'Payment result was superseded by a concurrent update',
        );
    }

    private function attemptManager(): PaymentAttemptManager
    {
        return $this->attemptManager ?? app(PaymentAttemptManager::class);
    }

    /**
     * Compensate every step that may have produced a side effect, in reverse
     * execution order, while preserving an auditable result for each attempt.
     *
     * When a failure operation is supplied, every compensation-log and
     * step-state write rechecks the operation token against the locked fresh
     * row. The first superseded write stops the pass so a stale execution
     * never rolls back shared inventory/order state under a replacement.
     *
     * @return list<StepResult>
     */
    public function compensate(CheckoutSession $session, ?PaymentFailureOperation $operation = null): array
    {
        $results = [];

        foreach (array_reverse($this->stepRegistry->getOrderedSteps()) as $step) {
            $identifier = $step->getIdentifier();
            $state = $session->getStepState($identifier);

            if (! in_array($state, [StepStatus::Completed, StepStatus::Processing, StepStatus::Failed], true)) {
                continue;
            }

            // Recheck before invoking the side effect, not only when the
            // result is recorded. A recovery that lands before this point
            // must leave no refund, void, or shared-resource release behind.
            if ($operation !== null && ! $this->attemptManager()->failureOperationStillAuthorized($session, $operation)) {
                return $results;
            }

            try {
                $result = $step->compensate($session);
            } catch (Throwable $e) {
                $message = $e->getMessage() !== '' ? $e->getMessage() : $e::class;
                $result = StepResult::failed(
                    stepIdentifier: $identifier,
                    message: 'Compensation failed',
                    errors: ['exception' => $message],
                );
            }

            if ($operation !== null) {
                $results[] = $result;

                if (! $this->attemptManager()->recordFailureCompensationIfOwned($session, $operation, $identifier, $result)) {
                    return $results;
                }

                continue;
            }

            $session->recordCompensation($identifier, $result);
            $results[] = $result;

            if ($result->isCompensated()) {
                $session->setStepState($identifier, StepStatus::RolledBack);
            }
        }

        return $results;
    }
}
