<?php

declare(strict_types=1);

namespace AIArmada\Cart\Exceptions;

use AIArmada\Cart\Cart;
use Exception;

final class CartConflictException extends CartException
{
    private ?Cart $conflictedCart = null;

    /** @var array<string, mixed>|null */
    private ?array $conflictedData = null;

    private int $attemptedVersion;

    private int $currentVersion;

    /**
     * @param  array<string, mixed>|null  $conflictedData
     */
    public function __construct(
        string $message,
        int $attemptedVersion,
        int $currentVersion,
        ?Cart $conflictedCart = null,
        ?array $conflictedData = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, 409, $previous);

        $this->attemptedVersion = $attemptedVersion;
        $this->currentVersion = $currentVersion;
        $this->conflictedCart = $conflictedCart;
        $this->conflictedData = $conflictedData;
    }

    /**
     * Get the cart instance that caused the conflict
     */
    public function getConflictedCart(): ?Cart
    {
        return $this->conflictedCart;
    }

    /**
     * Get the data that caused the conflict
     *
     * @return array<string, mixed>|null
     */
    public function getConflictedData(): ?array
    {
        return $this->conflictedData;
    }

    /**
     * Get the version that was attempted
     */
    public function getAttemptedVersion(): int
    {
        return $this->attemptedVersion;
    }

    /**
     * Get the current version in storage
     */
    public function getCurrentVersion(): int
    {
        return $this->currentVersion;
    }

    /**
     * Get version difference
     */
    public function getVersionDifference(): int
    {
        return $this->currentVersion - $this->attemptedVersion;
    }

    /**
     * Check if this is a minor conflict (1 version behind)
     */
    public function isMinorConflict(): bool
    {
        return $this->getVersionDifference() === 1;
    }

    /**
     * Get conflict resolution suggestions
     *
     * @return array<string>
     */
    public function getResolutionSuggestions(): array
    {
        $suggestions = [];

        if ($this->isMinorConflict()) {
            $suggestions[] = 'retry_with_refresh';
            $suggestions[] = 'merge_changes';
        } else {
            $suggestions[] = 'reload_cart';
            $suggestions[] = 'manual_resolution_required';
        }

        if ($this->conflictedCart) {
            $suggestions[] = 'compare_with_current';
        }

        return $suggestions;
    }

    /**
     * Convert to array for API responses
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'error' => 'cart_conflict',
            'message' => $this->getMessage(),
            'attempted_version' => $this->attemptedVersion,
            'current_version' => $this->currentVersion,
            'version_difference' => $this->getVersionDifference(),
            'is_minor_conflict' => $this->isMinorConflict(),
            'resolution_suggestions' => $this->getResolutionSuggestions(),
            'timestamp' => now()->toISOString(),
        ];
    }
}
