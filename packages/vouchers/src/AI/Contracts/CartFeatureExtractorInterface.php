<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI\Contracts;

use AIArmada\Cart\Cart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Interface for cart feature extraction.
 *
 * Implementations produce standardized feature vectors for ML/AI prediction.
 */
interface CartFeatureExtractorInterface
{
    /**
     * Extract all features from the given context.
     *
     * @return array<string, mixed>
     */
    public function extract(Cart $cart, ?Model $user = null, ?Request $request = null): array;

    /**
     * Extract cart-related features.
     *
     * @return array<string, mixed>
     */
    public function extractCartFeatures(Cart $cart): array;

    /**
     * Extract user-related features.
     *
     * @return array<string, mixed>
     */
    public function extractUserFeatures(?Model $user): array;

    /**
     * Extract session-related features.
     *
     * @return array<string, mixed>
     */
    public function extractSessionFeatures(?Request $request): array;

    /**
     * Extract time-based features.
     *
     * @return array<string, mixed>
     */
    public function extractTimeFeatures(): array;
}
