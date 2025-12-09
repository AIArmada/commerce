<?php

declare(strict_types=1);

namespace AIArmada\Cart\AI;

use AIArmada\Cart\Cart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * AI-powered product recommender for cart upselling and cross-selling.
 *
 * Analyzes cart contents and user behavior to suggest relevant products
 * that increase cart value and conversion rates.
 */
final class ProductRecommender
{
    /**
     * @var array<string, mixed>
     */
    private array $configuration;

    public function __construct()
    {
        $this->configuration = config('cart.ai.recommendations', [
            'enabled' => true,
            'max_recommendations' => 5,
            'cache_ttl_seconds' => 300,
            'min_confidence' => 0.3,
        ]);
    }

    /**
     * Get product recommendations for a cart.
     *
     * @return Collection<int, ProductRecommendation>
     */
    public function recommend(Cart $cart, ?string $userId = null): Collection
    {
        $cacheKey = "cart:recommendations:{$cart->getId()}";
        $cacheTtl = $this->configuration['cache_ttl_seconds'] ?? 300;

        return Cache::remember($cacheKey, $cacheTtl, function () use ($cart, $userId) {
            $recommendations = collect();

            $recommendations = $recommendations->merge($this->getFrequentlyBoughtTogether($cart));
            $recommendations = $recommendations->merge($this->getComplementaryProducts($cart));

            if ($userId) {
                $recommendations = $recommendations->merge($this->getPersonalizedRecommendations($userId, $cart));
            }

            $recommendations = $recommendations->merge($this->getUpsellProducts($cart));
            $recommendations = $recommendations->merge($this->getTrendingProducts($cart));

            return $this->deduplicateAndRank($recommendations, $cart);
        });
    }

    /**
     * Get recommendations for abandoned cart recovery.
     *
     * @return Collection<int, ProductRecommendation>
     */
    public function recommendForRecovery(Cart $cart): Collection
    {
        $recommendations = $this->recommend($cart);

        return $recommendations->filter(function (ProductRecommendation $rec) {
            return $rec->confidence >= 0.5 && $rec->type !== 'upsell';
        })->take(3);
    }

    /**
     * Record a recommendation click for learning.
     */
    public function recordClick(string $productId, string $cartId, string $recommendationType): void
    {
        $key = "recommendations:clicks:{$recommendationType}";
        $clicks = Cache::get($key, []);

        $clicks[] = [
            'product_id' => $productId,
            'cart_id' => $cartId,
            'timestamp' => now()->timestamp,
        ];

        $clicks = array_slice($clicks, -1000);

        Cache::put($key, $clicks, 86400 * 7);
    }

    /**
     * Record a recommendation conversion (added to cart).
     */
    public function recordConversion(string $productId, string $cartId, string $recommendationType): void
    {
        $key = "recommendations:conversions:{$recommendationType}";
        $conversions = Cache::get($key, []);

        $conversions[] = [
            'product_id' => $productId,
            'cart_id' => $cartId,
            'timestamp' => now()->timestamp,
        ];

        $conversions = array_slice($conversions, -1000);

        Cache::put($key, $conversions, 86400 * 7);

        $this->updateProductScore($productId, $recommendationType);
    }

    /**
     * Get recommendation statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $types = ['frequently_bought', 'complementary', 'personalized', 'upsell', 'trending'];
        $stats = [];

        foreach ($types as $type) {
            $clicks = Cache::get("recommendations:clicks:{$type}", []);
            $conversions = Cache::get("recommendations:conversions:{$type}", []);

            $clickCount = count($clicks);
            $conversionCount = count($conversions);
            $conversionRate = $clickCount > 0 ? $conversionCount / $clickCount : 0;

            $stats[$type] = [
                'clicks' => $clickCount,
                'conversions' => $conversionCount,
                'conversion_rate' => round($conversionRate * 100, 2),
            ];
        }

        return $stats;
    }

    /**
     * Get frequently bought together products.
     *
     * @return Collection<int, ProductRecommendation>
     */
    private function getFrequentlyBoughtTogether(Cart $cart): Collection
    {
        $cartItemIds = $cart->getItems()->pluck('id')->toArray();

        if (empty($cartItemIds)) {
            return collect();
        }

        $associations = $this->getProductAssociations($cartItemIds);

        return $associations->map(function ($association) {
            return new ProductRecommendation(
                productId: $association['product_id'],
                name: $association['name'] ?? 'Related Product',
                type: 'frequently_bought',
                confidence: $association['confidence'] ?? 0.5,
                reason: 'Frequently bought together',
                priceInCents: $association['price'] ?? 0,
                metadata: ['association_strength' => $association['strength'] ?? 0]
            );
        });
    }

    /**
     * Get complementary products based on category/attributes.
     *
     * @return Collection<int, ProductRecommendation>
     */
    private function getComplementaryProducts(Cart $cart): Collection
    {
        $recommendations = collect();

        foreach ($cart->getItems() as $item) {
            $attributes = $item->attributes->toArray();
            $category = $attributes['category'] ?? null;

            if ($category) {
                $complements = $this->getComplementsForCategory($category);

                foreach ($complements as $complement) {
                    $recommendations->push(new ProductRecommendation(
                        productId: $complement['product_id'],
                        name: $complement['name'] ?? 'Complementary Product',
                        type: 'complementary',
                        confidence: $complement['confidence'] ?? 0.4,
                        reason: "Complements {$item->name}",
                        priceInCents: $complement['price'] ?? 0,
                        metadata: ['source_item' => $item->id, 'category' => $category]
                    ));
                }
            }
        }

        return $recommendations;
    }

    /**
     * Get personalized recommendations based on user history.
     *
     * @return Collection<int, ProductRecommendation>
     */
    private function getPersonalizedRecommendations(string $userId, Cart $cart): Collection
    {
        $userPreferences = $this->getUserPreferences($userId);
        $cartItemIds = $cart->getItems()->pluck('id')->toArray();

        $recommendations = collect();

        foreach ($userPreferences as $preference) {
            if (in_array($preference['product_id'], $cartItemIds, true)) {
                continue;
            }

            $recommendations->push(new ProductRecommendation(
                productId: $preference['product_id'],
                name: $preference['name'] ?? 'Recommended for You',
                type: 'personalized',
                confidence: $preference['score'] ?? 0.6,
                reason: 'Based on your browsing history',
                priceInCents: $preference['price'] ?? 0,
                metadata: ['preference_score' => $preference['score'] ?? 0]
            ));
        }

        return $recommendations;
    }

    /**
     * Get upsell products (higher-tier alternatives).
     *
     * @return Collection<int, ProductRecommendation>
     */
    private function getUpsellProducts(Cart $cart): Collection
    {
        $recommendations = collect();
        $cartTotal = $cart->getRawTotal();

        foreach ($cart->getItems() as $item) {
            $attributes = $item->attributes->toArray();
            $category = $attributes['category'] ?? null;
            $tier = $attributes['tier'] ?? 'standard';

            if ($tier !== 'premium' && $category) {
                $upsells = $this->getUpsellsForProduct($item->id, $category);

                foreach ($upsells as $upsell) {
                    $priceDiff = ($upsell['price'] ?? 0) - $item->price;

                    if ($priceDiff > 0 && $priceDiff < $cartTotal * 0.3) {
                        $recommendations->push(new ProductRecommendation(
                            productId: $upsell['product_id'],
                            name: $upsell['name'] ?? 'Premium Alternative',
                            type: 'upsell',
                            confidence: 0.5,
                            reason: "Upgrade from {$item->name}",
                            priceInCents: $upsell['price'] ?? 0,
                            metadata: [
                                'original_item' => $item->id,
                                'price_difference' => $priceDiff,
                            ]
                        ));
                    }
                }
            }
        }

        return $recommendations;
    }

    /**
     * Get trending products.
     *
     * @return Collection<int, ProductRecommendation>
     */
    private function getTrendingProducts(Cart $cart): Collection
    {
        $cartItemIds = $cart->getItems()->pluck('id')->toArray();
        $trending = $this->getTrendingProductIds();

        return collect($trending)
            ->filter(fn ($product) => ! in_array($product['product_id'], $cartItemIds, true))
            ->map(fn ($product) => new ProductRecommendation(
                productId: $product['product_id'],
                name: $product['name'] ?? 'Trending Product',
                type: 'trending',
                confidence: $product['trend_score'] ?? 0.3,
                reason: 'Popular right now',
                priceInCents: $product['price'] ?? 0,
                metadata: ['trend_score' => $product['trend_score'] ?? 0]
            ))
            ->take(3);
    }

    /**
     * Deduplicate and rank recommendations.
     *
     * @param  Collection<int, ProductRecommendation>  $recommendations
     * @return Collection<int, ProductRecommendation>
     */
    private function deduplicateAndRank(Collection $recommendations, Cart $cart): Collection
    {
        $cartItemIds = $cart->getItems()->pluck('id')->toArray();
        $minConfidence = $this->configuration['min_confidence'] ?? 0.3;
        $maxRecommendations = $this->configuration['max_recommendations'] ?? 5;

        return $recommendations
            ->filter(fn (ProductRecommendation $r) => ! in_array($r->productId, $cartItemIds, true))
            ->filter(fn (ProductRecommendation $r) => $r->confidence >= $minConfidence)
            ->unique(fn (ProductRecommendation $r) => $r->productId)
            ->sortByDesc(fn (ProductRecommendation $r) => $r->confidence)
            ->take($maxRecommendations)
            ->values();
    }

    /**
     * Get product associations from cache/database.
     *
     * @param  array<string>  $productIds
     * @return Collection<int, array<string, mixed>>
     */
    private function getProductAssociations(array $productIds): Collection
    {
        $cacheKey = 'product:associations:' . md5(implode(',', $productIds));

        return Cache::remember($cacheKey, 3600, function () {
            return collect();
        });
    }

    /**
     * Get complements for a category.
     *
     * @return array<array<string, mixed>>
     */
    private function getComplementsForCategory(string $category): array
    {
        $key = "category:complements:{$category}";

        return Cache::get($key, []);
    }

    /**
     * Get user preferences.
     *
     * @return array<array<string, mixed>>
     */
    private function getUserPreferences(string $userId): array
    {
        $key = "user:preferences:{$userId}";

        return Cache::get($key, []);
    }

    /**
     * Get upsells for a product.
     *
     * @return array<array<string, mixed>>
     */
    private function getUpsellsForProduct(string $productId, string $category): array
    {
        $key = "product:upsells:{$productId}";

        return Cache::get($key, []);
    }

    /**
     * Get trending product IDs.
     *
     * @return array<array<string, mixed>>
     */
    private function getTrendingProductIds(): array
    {
        return Cache::get('products:trending', []);
    }

    /**
     * Update product recommendation score.
     */
    private function updateProductScore(string $productId, string $type): void
    {
        $key = "product:recommendation_score:{$productId}:{$type}";
        $score = Cache::get($key, 0);
        Cache::put($key, $score + 1, 86400 * 30);
    }
}
