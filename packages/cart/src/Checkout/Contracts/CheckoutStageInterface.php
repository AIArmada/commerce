<?php

declare(strict_types=1);

namespace AIArmada\Cart\Checkout\Contracts;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Checkout\StageResult;

/**
 * Interface for checkout pipeline stages.
 *
 * Each stage represents a step in the checkout process that can:
 * - Execute its logic
 * - Be conditionally skipped
 * - Support rollback on failure
 */
interface CheckoutStageInterface
{
    /**
     * Get the unique name of this stage.
     */
    public function getName(): string;

    /**
     * Determine if this stage should execute.
     *
     * @param  array<string, mixed>  $context
     */
    public function shouldExecute(Cart $cart, array $context): bool;

    /**
     * Execute the stage logic.
     *
     * @param  array<string, mixed>  $context
     */
    public function execute(Cart $cart, array $context): StageResult;

    /**
     * Check if this stage supports rollback.
     */
    public function supportsRollback(): bool;

    /**
     * Rollback any changes made by this stage.
     *
     * @param  array<string, mixed>  $context
     */
    public function rollback(Cart $cart, array $context): void;
}
