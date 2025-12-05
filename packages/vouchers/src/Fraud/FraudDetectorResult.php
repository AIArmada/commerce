<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Fraud;

/**
 * Result from an individual fraud detector.
 *
 * @property-read array<int, FraudSignal> $signals Detected fraud signals
 * @property-read string $detector The name of the detector that produced this result
 * @property-read float $executionTimeMs Time taken to run detection in milliseconds
 */
final readonly class FraudDetectorResult
{
    /**
     * @param  array<int, FraudSignal>  $signals  Detected fraud signals
     * @param  string  $detector  The name of the detector
     * @param  float  $executionTimeMs  Time taken in milliseconds
     */
    public function __construct(
        public array $signals,
        public string $detector,
        public float $executionTimeMs = 0.0,
    ) {}

    /**
     * Create an empty result (no signals detected).
     */
    public static function clean(string $detector, float $executionTimeMs = 0.0): self
    {
        return new self(
            signals: [],
            detector: $detector,
            executionTimeMs: $executionTimeMs,
        );
    }

    /**
     * Create a result with signals.
     *
     * @param  array<int, FraudSignal>  $signals
     */
    public static function withSignals(
        array $signals,
        string $detector,
        float $executionTimeMs = 0.0,
    ): self {
        return new self(
            signals: $signals,
            detector: $detector,
            executionTimeMs: $executionTimeMs,
        );
    }

    /**
     * Check if any signals were detected.
     */
    public function hasSignals(): bool
    {
        return !empty($this->signals);
    }

    /**
     * Get the count of signals.
     */
    public function getSignalCount(): int
    {
        return count($this->signals);
    }

    /**
     * Get the total score from all signals.
     */
    public function getTotalScore(): float
    {
        return array_reduce(
            $this->signals,
            fn (float $carry, FraudSignal $signal): float => $carry + $signal->score,
            0.0
        );
    }

    /**
     * Get the highest severity signal.
     */
    public function getHighestSeveritySignal(): ?FraudSignal
    {
        if (empty($this->signals)) {
            return null;
        }

        return array_reduce(
            $this->signals,
            fn (?FraudSignal $highest, FraudSignal $current): FraudSignal => $highest === null || $current->score > $highest->score
                    ? $current
                    : $highest,
            null
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'detector' => $this->detector,
            'signals' => array_map(
                fn (FraudSignal $signal): array => $signal->toArray(),
                $this->signals
            ),
            'signal_count' => $this->getSignalCount(),
            'total_score' => $this->getTotalScore(),
            'execution_time_ms' => $this->executionTimeMs,
        ];
    }
}
