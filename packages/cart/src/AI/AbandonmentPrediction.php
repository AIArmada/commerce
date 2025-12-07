<?php

declare(strict_types=1);

namespace AIArmada\Cart\AI;

use DateTimeInterface;

/**
 * Result of an abandonment prediction.
 */
final readonly class AbandonmentPrediction
{
    /**
     * @param  string  $cartId  The cart ID
     * @param  float  $probability  Abandonment probability (0.0 to 1.0)
     * @param  string  $riskLevel  Risk level: minimal, low, medium, high
     * @param  array<string, float>  $features  Feature values used for prediction
     * @param  array<Intervention>  $interventions  Recommended interventions
     * @param  DateTimeInterface  $predictedAt  When prediction was made
     */
    public function __construct(
        public string $cartId,
        public float $probability,
        public string $riskLevel,
        public array $features,
        public array $interventions,
        public DateTimeInterface $predictedAt
    ) {}

    /**
     * Check if cart is at high risk.
     */
    public function isHighRisk(): bool
    {
        return $this->riskLevel === 'high';
    }

    /**
     * Check if intervention is recommended.
     */
    public function needsIntervention(): bool
    {
        return ! empty($this->interventions);
    }

    /**
     * Get the top priority intervention.
     */
    public function getTopIntervention(): ?Intervention
    {
        return $this->interventions[0] ?? null;
    }

    /**
     * Get interventions of a specific type.
     *
     * @return array<Intervention>
     */
    public function getInterventionsByType(string $type): array
    {
        return array_filter($this->interventions, fn (Intervention $i) => $i->type === $type);
    }

    /**
     * Get the most significant feature contributing to abandonment risk.
     *
     * @return array{feature: string, value: float}|null
     */
    public function getMostSignificantFeature(): ?array
    {
        if (empty($this->features)) {
            return null;
        }

        $maxFeature = null;
        $maxValue = 0.0;

        foreach ($this->features as $feature => $value) {
            if ($value > $maxValue) {
                $maxValue = $value;
                $maxFeature = $feature;
            }
        }

        return $maxFeature ? ['feature' => $maxFeature, 'value' => $maxValue] : null;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cart_id' => $this->cartId,
            'probability' => $this->probability,
            'probability_percentage' => round($this->probability * 100, 1),
            'risk_level' => $this->riskLevel,
            'features' => $this->features,
            'interventions' => array_map(fn (Intervention $i) => $i->toArray(), $this->interventions),
            'predicted_at' => $this->predictedAt->format('Y-m-d H:i:s'),
        ];
    }
}
