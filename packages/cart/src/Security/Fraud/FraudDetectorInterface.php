<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Fraud;

/**
 * Interface for fraud detectors.
 *
 * Implementations analyze cart context and return detected fraud signals.
 */
interface FraudDetectorInterface
{
    /**
     * Get the unique name of this detector.
     */
    public function getName(): string;

    /**
     * Check if this detector is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Get the weight of this detector's signals.
     *
     * Weight is multiplied with signal scores to determine overall impact.
     * Default should be 1.0, higher values increase importance.
     */
    public function getWeight(): float;

    /**
     * Detect fraud signals in the given context.
     */
    public function detect(FraudContext $context): DetectorResult;
}
