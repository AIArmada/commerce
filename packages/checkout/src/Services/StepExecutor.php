<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Services;

use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Data\CheckoutResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Events\CheckoutStepCompleted;
use AIArmada\Checkout\Events\CheckoutStepFailed;
use AIArmada\Checkout\Exceptions\CheckoutStepException;
use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

final readonly class StepExecutor
{
    public function __construct(
        private CheckoutStepRegistryInterface $stepRegistry,
        private Dispatcher $events,
    ) {}

    public function run(
        CheckoutSession $session,
        ?string $fromStep = null,
    ): CheckoutResult {
        $startProcessing = $fromStep === null;

        foreach ($this->stepRegistry->getOrderedSteps() as $step) {
            if (! $startProcessing && $step->getIdentifier() === $fromStep) {
                $startProcessing = true;

                continue;
            }

            if (! $startProcessing) {
                continue;
            }

            $stepState = $session->getStepState($step->getIdentifier());

            if ($stepState === StepStatus::Completed || $stepState === StepStatus::Skipped) {
                continue;
            }

            if ($step->canSkip($session)) {
                $session->setStepState($step->getIdentifier(), StepStatus::Skipped);

                continue;
            }

            $result = $this->processStep($session, $step);

            if (! $result->isSuccessful()) {
                $this->compensate($session);

                return CheckoutResult::failed($session, $result->message ?? 'Step failed', $result->errors);
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
            message: 'Pipeline completed',
        );
    }

    public function processStep(CheckoutSession $session, CheckoutStepInterface $step): StepResult
    {
        $identifier = $step->getIdentifier();

        foreach ($step->getDependencies() as $dependency) {
            $depState = $session->getStepState($dependency);
            if ($depState !== StepStatus::Completed && $depState !== StepStatus::Skipped) {
                throw CheckoutStepException::dependencyNotMet($identifier, $dependency);
            }
        }

        $errors = $step->validate($session);
        if (! empty($errors)) {
            $session->setStepState($identifier, StepStatus::Failed);
            $this->events->dispatch(new CheckoutStepFailed($session, $identifier, $errors));

            return StepResult::failed($identifier, 'Validation failed', $errors);
        }

        $session->setStepState($identifier, StepStatus::Processing);
        $session->update(['current_step' => $identifier]);

        try {
            $result = $step->handle($session);
        } catch (Throwable $e) {
            $message = $e->getMessage() !== '' ? $e->getMessage() : $e::class;
            $session->setStepState($identifier, StepStatus::Failed);
            $this->events->dispatch(new CheckoutStepFailed(
                $session,
                $identifier,
                ['exception' => $message],
            ));

            throw $e;
        }

        $session->setStepState($identifier, $result->status);

        if ($result->isSuccessful()) {
            $this->events->dispatch(new CheckoutStepCompleted($session, $identifier, $result->data));
        } else {
            $this->events->dispatch(new CheckoutStepFailed($session, $identifier, $result->errors));
        }

        return $result;
    }

    /**
     * Compensate every step that may have produced a side effect, in reverse
     * execution order, while preserving an auditable result for each attempt.
     *
     * @return list<StepResult>
     */
    public function compensate(CheckoutSession $session): array
    {
        $results = [];

        foreach (array_reverse($this->stepRegistry->getOrderedSteps()) as $step) {
            $identifier = $step->getIdentifier();
            $state = $session->getStepState($identifier);

            if (! in_array($state, [StepStatus::Completed, StepStatus::Processing, StepStatus::Failed], true)) {
                continue;
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

            $session->recordCompensation($identifier, $result);
            $results[] = $result;

            if ($result->isCompensated()) {
                $session->setStepState($identifier, StepStatus::RolledBack);
            }
        }

        return $results;
    }
}
