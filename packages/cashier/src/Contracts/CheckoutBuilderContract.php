<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

use Illuminate\Http\RedirectResponse;

/**
 * Contract for checkout builders.
 */
interface CheckoutBuilderContract
{
    /**
     * Set the customer for the checkout.
     */
    public function customer(BillableContract $customer): static;

    /**
     * Add a price/product to the checkout.
     */
    public function price(string $price, int $quantity = 1): static;

    /**
     * Add multiple prices/products to the checkout.
     *
     * @param  array<string, int>  $prices  Price ID => Quantity
     */
    public function prices(array $prices): static;

    /**
     * Set the success URL.
     */
    public function successUrl(string $url): static;

    /**
     * Set the cancel URL.
     */
    public function cancelUrl(string $url): static;

    /**
     * Set the mode (payment, subscription, setup).
     */
    public function mode(string $mode): static;

    /**
     * Apply a coupon or promotion code.
     */
    public function coupon(string $coupon): static;

    /**
     * Allow promotion codes.
     */
    public function allowPromotionCodes(bool $allow = true): static;

    /**
     * Collect tax ID from the customer.
     */
    public function collectTaxIds(bool $collect = true): static;

    /**
     * Set metadata for the checkout.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): static;

    /**
     * Set the trial period in days.
     */
    public function trialDays(int $days): static;

    /**
     * Create the checkout session.
     */
    public function create(): CheckoutContract;

    /**
     * Create and redirect to the checkout session.
     */
    public function redirect(): RedirectResponse;
}
