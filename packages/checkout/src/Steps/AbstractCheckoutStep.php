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

    public function rollback(CheckoutSession $session): void
    {
        // Default: no rollback action
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
}
