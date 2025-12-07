<?php

declare(strict_types=1);

namespace AIArmada\Cart\AI;

/**
 * Represents a product recommendation.
 */
final readonly class ProductRecommendation
{
    /**
     * @param  string  $productId  Product ID
     * @param  string  $name  Product name
     * @param  string  $type  Recommendation type: frequently_bought, complementary, personalized, upsell, trending
     * @param  float  $confidence  Confidence score (0.0 to 1.0)
     * @param  string  $reason  Human-readable reason
     * @param  int  $priceInCents  Product price in cents
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public string $productId,
        public string $name,
        public string $type,
        public float $confidence,
        public string $reason,
        public int $priceInCents = 0,
        public array $metadata = []
    ) {}

    /**
     * Check if this is a high confidence recommendation.
     */
    public function isHighConfidence(): bool
    {
        return $this->confidence >= 0.7;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'name' => $this->name,
            'type' => $this->type,
            'confidence' => $this->confidence,
            'confidence_percentage' => round($this->confidence * 100, 1),
            'reason' => $this->reason,
            'price_in_cents' => $this->priceInCents,
            'metadata' => $this->metadata,
        ];
    }
}
