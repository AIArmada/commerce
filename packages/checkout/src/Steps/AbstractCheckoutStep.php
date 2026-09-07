<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;

abstract class AbstractCheckoutStep implements CheckoutStepInterface
{
    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return [];
    }

    public function canSkip(CheckoutSession $session): bool
    {
        return false;
    }

    public function compensate(CheckoutSession $session): StepResult
    {
        return StepResult::compensated(
            stepIdentifier: $this->getIdentifier(),
            message: 'No compensation required',
            data: ['operation' => 'none'],
        );
    }

    /**
     * @return array<string, string>
     */
    public function validate(CheckoutSession $session): array
    {
        return [];
    }

    protected function success(?string $message = null, array $data = []): StepResult
    {
        return StepResult::success($this->getIdentifier(), $message, $data);
    }

    protected function skipped(?string $message = null): StepResult
    {
        return StepResult::skipped($this->getIdentifier(), $message);
    }

    protected function failed(string $message, array $errors = []): StepResult
    {
        return StepResult::failed($this->getIdentifier(), $message, $errors);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function compensated(string $message, array $data = []): StepResult
    {
        return StepResult::compensated($this->getIdentifier(), $message, $data);
    }
}
