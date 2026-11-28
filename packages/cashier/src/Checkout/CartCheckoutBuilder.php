<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Checkout;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CheckoutBuilderContract;
use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Contracts\DynamicCheckoutBuilderContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Exceptions\CheckoutException;
use AIArmada\Cashier\Exceptions\InsufficientStockException;
use AIArmada\Cashier\Support\ActionGuard;
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use AIArmada\Inventory\Cart\CartManagerWithInventory;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Checkout builder that orchestrates cart → payment flow.
 *
 * Provides a fluent API for creating checkout sessions from carts:
 * - Converts cart items to payment line items
 * - Handles inventory allocation before payment
 * - Preserves cart metadata for post-payment processing
 * - Supports multiple payment gateways
 */
final class CartCheckoutBuilder
{
    private Cart $cart;

    private GatewayContract $gateway;

    private ?BillableContract $customer = null;

    private ?string $successUrl = null;

    private ?string $cancelUrl = null;

    private string $mode = 'payment';

    private bool $allocateInventory = true;

    private int $inventoryTtl = 30;

    private bool $validateStock = true;

    /** @var array<string, mixed> */
    private array $metadata = [];

    public function __construct(Cart $cart, GatewayContract $gateway)
    {
        $this->cart = $cart;
        $this->gateway = $gateway;
        $this->allocateInventory = (bool) config('checkout.integrations.inventory.enabled', true);
        $ttlSeconds = (int) config('checkout.integrations.inventory.reservation_ttl', 900);
        $this->inventoryTtl = max(1, (int) ceil($ttlSeconds / 60));
        $this->validateStock = (bool) config('checkout.integrations.inventory.validate_stock', true);
    }

    /**
     * Set the customer for the checkout.
     */
    public function customer(BillableContract $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    /**
     * Set the success redirect URL.
     */
    public function successUrl(string $url): self
    {
        $this->successUrl = $url;

        return $this;
    }

    /**
     * Set the cancel redirect URL.
     */
    public function cancelUrl(string $url): self
    {
        $this->cancelUrl = $url;

        return $this;
    }

    /**
     * Set the checkout mode.
     */
    public function mode(string $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * Configure inventory allocation before payment.
     */
    public function allocateInventory(bool $allocate = true, int $ttlMinutes = 30): self
    {
        $this->allocateInventory = $allocate;
        $this->inventoryTtl = $ttlMinutes;

        return $this;
    }

    /**
     * Disable inventory allocation.
     */
    public function withoutInventoryAllocation(): self
    {
        $this->allocateInventory = false;

        return $this;
    }

    /**
     * Configure stock validation.
     */
    public function validateStock(bool $validate = true): self
    {
        $this->validateStock = $validate;

        return $this;
    }

    /**
     * Add metadata to the checkout.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Process the checkout: validate, allocate inventory, create payment.
     *
     * Stock validation and inventory allocation run inside a transaction.
     * The gateway call runs outside it so database locks are never held
     * across network I/O; a gateway failure releases the allocation.
     *
     * @throws CheckoutException
     */
    public function process(): CheckoutContract
    {
        if ($this->customer === null) {
            throw new InvalidArgumentException('Customer is required for checkout. Call customer() first.');
        }

        ActionGuard::assertBillableInScope($this->customer);

        DB::transaction(function (): void {
            // Validate stock if configured
            if ($this->validateStock) {
                $this->performStockValidation();
            }

            // Allocate inventory if configured
            if ($this->allocateInventory) {
                $this->performInventoryAllocation();
            }
        });

        try {
            return $this->createCheckoutSession();
        } catch (Throwable $e) {
            $this->releaseInventoryAllocation();

            throw $e;
        }
    }

    private function performStockValidation(): void
    {
        $manager = $this->inventoryManager();

        if ($manager === null) {
            return;
        }

        $validation = $manager->validateInventory($this->cart);

        if (! $validation['available']) {
            throw new InsufficientStockException('Insufficient stock for some items', $validation['issues']);
        }
    }

    private function performInventoryAllocation(): void
    {
        $this->inventoryManager()?->allocateAllInventory($this->inventoryTtl, $this->cart);
    }

    private function releaseInventoryAllocation(): void
    {
        if (! $this->allocateInventory || ! app()->bound(InventoryAllocationService::class)) {
            return;
        }

        $id = $this->cart->getId() ?? sprintf('%s_%s', $this->cart->getIdentifier(), $this->cart->instance());
        app(InventoryAllocationService::class)->releaseAllForCart($id);
    }

    private function inventoryManager(): ?CartManagerWithInventory
    {
        if (! app()->bound(InventoryAllocationService::class) || ! app()->bound('cart')) {
            return null;
        }

        /** @var CartManagerInterface $manager */
        $manager = app('cart');

        return CartManagerWithInventory::fromCartManager($manager);
    }

    /**
     * Create the checkout session with the gateway.
     */
    private function createCheckoutSession(): CheckoutContract
    {
        if (! $this->customer) {
            throw new InvalidArgumentException('Customer is required for checkout. Call customer() first.');
        }

        // Build checkout with gateway
        $checkoutBuilder = $this->gateway->checkout($this->customer);

        // Add cart items as line items
        foreach ($this->cart->getItems() as $item) {
            $this->addItemToCheckout($checkoutBuilder, $item);
        }

        // Set URLs
        if ($this->successUrl) {
            $checkoutBuilder->successUrl($this->successUrl);
        }

        if ($this->cancelUrl) {
            $checkoutBuilder->cancelUrl($this->cancelUrl);
        }

        // Set mode
        $checkoutBuilder->mode($this->mode);

        // Build metadata including cart reference
        $metadata = array_merge($this->metadata, [
            config('cashier.cart.metadata_key', 'cart_id') => $this->cart->getId(),
            'cart_total' => $this->cart->total(),
            'cart_items_count' => $this->cart->count(),
        ]);

        $checkoutBuilder->metadata($metadata);

        return $checkoutBuilder->create();
    }

    /**
     * Add a cart item to the checkout builder.
     */
    private function addItemToCheckout(CheckoutBuilderContract $builder, LineItemInterface $item): void
    {
        // For gateways requiring pre-configured prices (like Stripe)
        // we look for a price_id in the item attributes.
        $metadata = $item->getLineItemMetadata();
        $priceId = $metadata['price_id'] ?? null;
        $quantity = (int) $item->getLineItemQuantity();

        if (is_string($priceId) && $priceId !== '') {
            $builder->price($priceId, $quantity);

            return;
        }

        // For gateways with dynamic pricing (like CHIP) the real unit
        // amount travels with the item name so the purchase never posts 0.
        if ($builder instanceof DynamicCheckoutBuilderContract) {
            $builder->product(
                $item->getLineItemName(),
                (int) $item->getLineItemPrice()->getAmount(),
                $quantity,
            );

            return;
        }

        $builder->price($item->getLineItemId(), $quantity);
    }
}
