<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Fraud\Detectors;

use AIArmada\Vouchers\Fraud\Contracts\FraudDetectorInterface;
use AIArmada\Vouchers\Fraud\FraudDetectorResult;
use AIArmada\Vouchers\Fraud\FraudSignal;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for fraud detectors providing common functionality.
 */
abstract class AbstractFraudDetector implements FraudDetectorInterface
{
    /** @var array<int, FraudSignal> */
    protected array $signals = [];

    protected bool $enabled = true;

    public function detect(
        string $code,
        object $cart,
        ?Model $user = null,
        array $context = [],
    ): FraudDetectorResult {
        $this->signals = [];

        if (!$this->isEnabled()) {
            return FraudDetectorResult::clean($this->getName());
        }

        $startTime = microtime(true);

        $this->analyze($code, $cart, $user, $context);

        $executionTimeMs = (microtime(true) - $startTime) * 1000;

        return new FraudDetectorResult(
            signals: $this->signals,
            detector: $this->getName(),
            executionTimeMs: $executionTimeMs,
        );
    }

    /**
     * Perform the actual fraud analysis.
     *
     * Subclasses implement this method to detect specific fraud signals.
     *
     * @param  string  $code  The voucher code being redeemed
     * @param  object  $cart  The cart associated with the redemption
     * @param  Model|null  $user  The user attempting the redemption
     * @param  array<string, mixed>  $context  Additional context
     */
    abstract protected function analyze(
        string $code,
        object $cart,
        ?Model $user,
        array $context,
    ): void;

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable or disable this detector.
     */
    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Add a fraud signal to the result.
     */
    protected function addSignal(FraudSignal $signal): void
    {
        $this->signals[] = $signal;
    }

    /**
     * Get a context value with a default.
     *
     * @param  array<string, mixed>  $context
     */
    protected function getContextValue(array $context, string $key, mixed $default = null): mixed
    {
        return $context[$key] ?? $default;
    }

    /**
     * Get the user ID if available.
     */
    protected function getUserId(?Model $user): ?string
    {
        if ($user === null) {
            return null;
        }

        return (string) $user->getKey();
    }
}
