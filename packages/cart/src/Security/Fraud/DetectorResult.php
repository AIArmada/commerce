<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Fraud;

/**
 * Result from a single fraud detector.
 */
final readonly class DetectorResult
{
    /**
     * @param  string  $detector  Name of the detector
     * @param  array<FraudSignal>  $signals  Detected signals
     * @param  bool  $passed  Whether the check passed (no signals)
     * @param  int  $executionTimeMs  Time taken to run detection
     * @param  array<string, mixed>  $debugInfo  Additional debug information
     */
    public function __construct(
        public string $detector,
        public array $signals,
        public bool $passed,
        public int $executionTimeMs = 0,
        public array $debugInfo = []
    ) {}

    /**
     * Create a passing result with no signals.
     */
    public static function pass(string $detector, int $executionTimeMs = 0): self
    {
        return new self(
            detector: $detector,
            signals: [],
            passed: true,
            executionTimeMs: $executionTimeMs
        );
    }

    /**
     * Create a result with signals.
     *
     * @param  array<FraudSignal>  $signals
     * @param  array<string, mixed>  $debugInfo
     */
    public static function withSignals(
        string $detector,
        array $signals,
        int $executionTimeMs = 0,
        array $debugInfo = []
    ): self {
        return new self(
            detector: $detector,
            signals: $signals,
            passed: empty($signals),
            executionTimeMs: $executionTimeMs,
            debugInfo: $debugInfo
        );
    }

    /**
     * Get total score from all signals.
     */
    public function getTotalScore(): int
    {
        return array_sum(array_map(fn (FraudSignal $s) => $s->score, $this->signals));
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
            fn (?FraudSignal $carry, FraudSignal $signal) => $carry === null || $signal->score > $carry->score
                ? $signal
                : $carry
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'detector' => $this->detector,
            'passed' => $this->passed,
            'signal_count' => count($this->signals),
            'total_score' => $this->getTotalScore(),
            'execution_time_ms' => $this->executionTimeMs,
            'signals' => array_map(fn (FraudSignal $s) => $s->toArray(), $this->signals),
        ];
    }
}
