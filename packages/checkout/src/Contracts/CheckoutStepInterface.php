<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Contracts;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;

interface CheckoutStepInterface
{
    /**
     * Get the unique identifier for this step.
     */
    public function getIdentifier(): string;

    /**
     * Get the display name for this step.
     */
    public function getName(): string;

    /**
     * Validate that the step can be executed.
     *
     * @return array<string, string> Validation errors keyed by field name
     */
    public function validate(CheckoutSession $session): array;

    /**
     * Execute the step logic.
     */
    public function handle(CheckoutSession $session): StepResult;

    /**
     * Determine if this step can be skipped.
     */
    public function canSkip(CheckoutSession $session): bool;

    /**
     * Rollback any changes made by this step.
     */
    public function rollback(CheckoutSession $session): void;

    /**
     * Get the dependencies (other step identifiers) that must run before this step.
     *
     * @return array<string>
     */
    public function getDependencies(): array;
}
