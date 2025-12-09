<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Fraud;

use AIArmada\Vouchers\Fraud\Enums\FraudRiskLevel;

/**
 * Value object representing the result of a fraud analysis.
 *
 * @property-read float $fraudScore Score from 0.0 (safe) to 1.0 (definitely fraud)
 * @property-read FraudRiskLevel $riskLevel The calculated risk level
 * @property-read array<int, FraudSignal> $signals Detected fraud signals
 * @property-read bool $shouldBlock Whether redemption should be blocked
 * @property-read string|null $blockReason Human-readable reason for blocking
 * @property-read bool $requiresReview Whether manual review is needed
 */
final readonly class FraudAnalysis
{
    /**
     * @param  float  $fraudScore  Score from 0.0 (safe) to 1.0 (definitely fraud)
     * @param  FraudRiskLevel  $riskLevel  The calculated risk level
     * @param  array<int, FraudSignal>  $signals  Detected fraud signals
     * @param  bool  $shouldBlock  Whether redemption should be blocked
     * @param  string|null  $blockReason  Human-readable reason for blocking
     * @param  bool  $requiresReview  Whether manual review is needed
     */
    public function __construct(
        public float $fraudScore,
        public FraudRiskLevel $riskLevel,
        public array $signals,
        public bool $shouldBlock,
        public ?string $blockReason = null,
        public bool $requiresReview = false,
    ) {}

    /**
     * Create an analysis result indicating no fraud detected.
     */
    public static function clean(): self
    {
        return new self(
            fraudScore: 0.0,
            riskLevel: FraudRiskLevel::Low,
            signals: [],
            shouldBlock: false,
            blockReason: null,
            requiresReview: false,
        );
    }

    /**
     * Create an analysis result from a set of signals.
     *
     * @param  array<int, FraudSignal>  $signals
     */
    public static function fromSignals(array $signals, float $blockThreshold = 0.8): self
    {
        if (empty($signals)) {
            return self::clean();
        }

        $totalScore = array_reduce(
            $signals,
            fn (float $carry, FraudSignal $signal): float => $carry + $signal->score,
            0.0
        );

        // Normalize score to 0.0-1.0 range (max 100 points from signals)
        $fraudScore = min(1.0, $totalScore / 100);
        $riskLevel = FraudRiskLevel::fromScore($fraudScore);
        $shouldBlock = $fraudScore >= $blockThreshold;

        return new self(
            fraudScore: $fraudScore,
            riskLevel: $riskLevel,
            signals: $signals,
            shouldBlock: $shouldBlock,
            blockReason: $shouldBlock ? self::summarizeSignals($signals) : null,
            requiresReview: $riskLevel->requiresReview(),
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
     * Get signals by category.
     *
     * @return array<string, array<int, FraudSignal>>
     */
    public function getSignalsByCategory(): array
    {
        $grouped = [];

        foreach ($this->signals as $signal) {
            $category = $signal->type->getCategory();
            if (! isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $signal;
        }

        return $grouped;
    }

    /**
     * Check if a specific signal type was detected.
     */
    public function hasSignal(Enums\FraudSignalType $type): bool
    {
        foreach ($this->signals as $signal) {
            if ($signal->type === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the count of signals.
     */
    public function getSignalCount(): int
    {
        return count($this->signals);
    }

    /**
     * Check if the analysis detected any issues.
     */
    public function hasIssues(): bool
    {
        return ! empty($this->signals);
    }

    /**
     * Check if the analysis is clean (no fraud detected).
     */
    public function isClean(): bool
    {
        return empty($this->signals) && $this->fraudScore === 0.0;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fraud_score' => $this->fraudScore,
            'risk_level' => $this->riskLevel->value,
            'signals' => array_map(
                fn (FraudSignal $signal): array => $signal->toArray(),
                $this->signals
            ),
            'should_block' => $this->shouldBlock,
            'block_reason' => $this->blockReason,
            'requires_review' => $this->requiresReview,
            'signal_count' => $this->getSignalCount(),
        ];
    }

    /**
     * Summarize signals into a human-readable string.
     *
     * @param  array<int, FraudSignal>  $signals
     */
    private static function summarizeSignals(array $signals): string
    {
        if (empty($signals)) {
            return 'Unknown fraud signals detected';
        }

        // Sort by score descending
        usort($signals, fn (FraudSignal $a, FraudSignal $b): int => $b->score <=> $a->score);

        // Take top 3 signals
        $topSignals = array_slice($signals, 0, 3);
        $labels = array_map(
            fn (FraudSignal $signal): string => $signal->type->getLabel(),
            $topSignals
        );

        $summary = implode(', ', $labels);

        if (count($signals) > 3) {
            $summary .= ' and ' . (count($signals) - 3) . ' more';
        }

        return $summary;
    }
}
