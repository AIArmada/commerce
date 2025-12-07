<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Fraud;

/**
 * Result of fraud analysis.
 */
final readonly class FraudAnalysisResult
{
    /**
     * @param  int  $score  Normalized risk score (0-100)
     * @param  string  $riskLevel  Risk level: minimal, low, medium, high
     * @param  array<FraudSignal>  $signals  Detected fraud signals
     * @param  array<string, DetectorResult>  $detectorResults  Results from each detector
     * @param  bool  $shouldBlock  Whether transaction should be blocked
     * @param  bool  $shouldReview  Whether transaction requires manual review
     * @param  array<string>  $recommendations  Recommended actions
     */
    public function __construct(
        public int $score,
        public string $riskLevel,
        public array $signals,
        public array $detectorResults,
        public bool $shouldBlock,
        public bool $shouldReview,
        public array $recommendations
    ) {}

    /**
     * Check if the result indicates a clean transaction.
     */
    public function isClean(): bool
    {
        return $this->riskLevel === 'minimal' && empty($this->signals);
    }

    /**
     * Get signals grouped by detector.
     *
     * @return array<string, array<FraudSignal>>
     */
    public function getSignalsByDetector(): array
    {
        $grouped = [];

        foreach ($this->signals as $signal) {
            $grouped[$signal->detector][] = $signal;
        }

        return $grouped;
    }

    /**
     * Get signals above a certain score threshold.
     *
     * @return array<FraudSignal>
     */
    public function getHighScoreSignals(int $threshold = 50): array
    {
        return array_filter($this->signals, fn (FraudSignal $s) => $s->score >= $threshold);
    }

    /**
     * Get a summary for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'risk_level' => $this->riskLevel,
            'signal_count' => count($this->signals),
            'should_block' => $this->shouldBlock,
            'should_review' => $this->shouldReview,
            'recommendations' => $this->recommendations,
            'signals' => array_map(fn (FraudSignal $s) => $s->toArray(), $this->signals),
        ];
    }
}
