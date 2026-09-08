<?php

declare(strict_types=1);

namespace AIArmada\Chip\Builders;

use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use Akaunting\Money\Money;

final class PurchaseBuilder
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    private ?string $idempotencyKey = null;

    public function __construct(
        private ChipCollectService $service
    ) {}

    /**
     * Set the brand ID
     */
    public function brand(string $brandId): self
    {
        $this->data['brand_id'] = $brandId;

        return $this;
    }

    /**
     * Set purchase currency
     */
    public function currency(string $currency): self
    {
        $currency = mb_strtoupper(mb_trim($currency));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new ChipValidationException('Purchase currency must be a three-letter ISO 4217 code.');
        }

        $existingCurrency = $this->data['purchase']['currency'] ?? null;

        if ($existingCurrency !== null && $existingCurrency !== $currency && ! empty($this->data['purchase']['products'])) {
            throw new ChipValidationException('Purchase currency cannot change after products have been added.');
        }

        $this->data['purchase']['currency'] = $currency;

        return $this;
    }

    /**
     * Add a product using Money objects.
     *
     * This is the preferred way to add products as it ensures type-safe
     * currency handling across all commerce packages.
     *
     * @throws ChipValidationException If price is negative
     */
    public function addProductMoney(
        string $name,
        Money $price,
        string | float | int $quantity = 1,
        ?Money $discount = null,
        float $taxPercent = 0,
        ?string $category = null
    ): self {
        $priceAmount = (int) $price->getAmount();

        if ($priceAmount < 0) {
            throw new ChipValidationException('Product price cannot be negative', ['price' => $priceAmount]);
        }

        $currency = $price->getCurrency()->getCurrency();
        $purchaseCurrency = $this->purchaseCurrency();

        if ($currency !== $purchaseCurrency) {
            throw new ChipValidationException('Product price currency must match the purchase currency.', [
                'purchase_currency' => $purchaseCurrency,
                'product_currency' => $currency,
            ]);
        }

        $normalizedQuantity = $this->normalizeQuantity($quantity);

        if ($discount !== null && $discount->getCurrency()->getCurrency() !== $purchaseCurrency) {
            throw new ChipValidationException('Product discount currency must match the purchase currency.', [
                'purchase_currency' => $purchaseCurrency,
                'discount_currency' => $discount->getCurrency()->getCurrency(),
            ]);
        }

        $product = [
            'name' => $name,
            'price' => $priceAmount,
            'quantity' => (string) $normalizedQuantity,
        ];

        if ($discount !== null && $discount->getAmount() > 0) {
            $product['discount'] = (int) $discount->getAmount();
        }

        if ($taxPercent > 0) {
            $product['tax_percent'] = $taxPercent;
        }

        if ($category !== null) {
            $product['category'] = $category;
        }

        $this->data['purchase']['products'][] = $product;

        return $this;
    }

    /**
     * Add a Product data object directly.
     */
    public function addProductObject(ProductData $product): self
    {
        $purchaseCurrency = $this->purchaseCurrency();

        if ($product->getCurrency() !== $purchaseCurrency) {
            throw new ChipValidationException('Product currency must match the purchase currency.', [
                'purchase_currency' => $purchaseCurrency,
                'product_currency' => $product->getCurrency(),
            ]);
        }

        $productData = $product->toArray();
        $productData['quantity'] = (string) $this->normalizeQuantity($product->quantity);

        $this->data['purchase']['products'][] = $productData;

        return $this;
    }

    /**
     * Add a product from a LineItemInterface (universal contract).
     */
    public function addLineItem(LineItemInterface $item): self
    {
        return $this->addProductMoney(
            name: $item->getLineItemName(),
            price: $item->getLineItemPrice(),
            quantity: $item->getLineItemQuantity(),
            discount: $item->getLineItemDiscount(),
            taxPercent: $item->getLineItemTaxPercent(),
            category: $item->getLineItemCategory()
        );
    }

    /**
     * Add a product using price in cents (convenience method).
     *
     * This creates a Money object internally using the explicitly configured purchase currency.
     * Use addProductMoney() for direct currency control.
     *
     * @throws ChipValidationException If price is negative
     */
    public function addProductCents(
        string $name,
        int $priceInCents,
        string | float | int $quantity = 1,
        int $discountInCents = 0,
        float $taxPercent = 0,
        ?string $category = null
    ): self {
        $currency = $this->purchaseCurrency();

        return $this->addProductMoney(
            name: $name,
            price: Money::{$currency}($priceInCents),
            quantity: $quantity,
            discount: $discountInCents > 0 ? Money::{$currency}($discountInCents) : null,
            taxPercent: $taxPercent,
            category: $category
        );
    }

    /**
     * Build purchase from a CheckoutableInterface (Cart, Order, etc.).
     *
     * This is the recommended way to create a purchase from a cart or order
     * as it uses the universal contract for maximum portability.
     */
    public function fromCheckoutable(CheckoutableInterface $checkoutable): self
    {
        $currency = mb_strtoupper(mb_trim($checkoutable->getCheckoutCurrency()));
        $subtotal = $checkoutable->getCheckoutSubtotal();
        $checkoutDiscount = $checkoutable->getCheckoutDiscount();
        $tax = $checkoutable->getCheckoutTax();
        $total = $checkoutable->getCheckoutTotal();

        $this->assertMoneyCurrency($subtotal, $currency, 'checkout subtotal');
        $this->assertMoneyCurrency($checkoutDiscount, $currency, 'checkout discount');
        $this->assertMoneyCurrency($tax, $currency, 'checkout tax');
        $this->assertMoneyCurrency($total, $currency, 'checkout total');
        $this->currency($currency);
        $this->reference($checkoutable->getCheckoutReference());

        /** @var list<LineItemInterface> $lineItems */
        $lineItems = iterator_to_array($checkoutable->getCheckoutLineItems(), false);
        $lineItemsSubtotal = 0;

        foreach ($lineItems as $item) {
            $quantity = $this->normalizeQuantity($item->getLineItemQuantity());
            $price = $item->getLineItemPrice();
            $lineItemDiscount = $item->getLineItemDiscount();

            $this->assertMoneyCurrency($price, $currency, 'line item price');
            $this->assertMoneyCurrency($lineItemDiscount, $currency, 'line item discount');

            $lineItemsSubtotal += (int) $price->getAmount() * $quantity;
        }

        $subtotalAmount = (int) $subtotal->getAmount();
        $discountAmount = (int) $checkoutDiscount->getAmount();
        $taxAmount = (int) $tax->getAmount();
        $totalAmount = (int) $total->getAmount();

        if ($lineItemsSubtotal !== $subtotalAmount) {
            throw new ChipValidationException('Checkout line items do not match the checkout subtotal.', [
                'line_items_subtotal' => $lineItemsSubtotal,
                'checkout_subtotal' => $subtotalAmount,
            ]);
        }

        $calculatedTotal = $subtotalAmount - $discountAmount + $taxAmount;

        if ($calculatedTotal !== $totalAmount) {
            throw new ChipValidationException('Checkout totals do not reconcile.', [
                'calculated_total' => $calculatedTotal,
                'checkout_total' => $totalAmount,
            ]);
        }

        foreach ($lineItems as $item) {
            $this->addLineItem($item);
        }

        $this->data['purchase']['subtotal_override'] = $subtotalAmount;
        $this->data['purchase']['total_discount_override'] = $discountAmount;
        $this->data['purchase']['total_tax_override'] = $taxAmount;
        $this->data['purchase']['total_override'] = $totalAmount;
        $this->data['purchase']['total'] = $totalAmount;

        if ($checkoutable->getCheckoutNotes() !== null) {
            $this->notes($checkoutable->getCheckoutNotes());
        }

        $metadata = $checkoutable->getCheckoutMetadata();
        if (! empty($metadata)) {
            $this->metadata($metadata);
        }

        return $this;
    }

    /**
     * Build purchase with customer from interfaces.
     */
    public function fromCheckoutWithCustomer(
        CheckoutableInterface $checkoutable,
        CustomerInterface $customer
    ): self {
        $this->fromCheckoutable($checkoutable);
        $this->fromCustomer($customer);

        return $this;
    }

    /**
     * Set customer details from a CustomerInterface.
     */
    public function fromCustomer(CustomerInterface $customer): self
    {
        $this->customer(
            email: $customer->getCustomerEmail(),
            fullName: $customer->getCustomerName(),
            phone: $customer->getCustomerPhone(),
            country: $customer->getCustomerCountry()
        );

        // Set billing address if available
        if ($customer->getBillingStreetAddress() !== null) {
            $this->billingAddress(
                streetAddress: $customer->getBillingStreetAddress(),
                city: $customer->getBillingCity() ?? '',
                zipCode: $customer->getBillingPostalCode() ?? '',
                state: $customer->getBillingState(),
                country: $customer->getBillingCountry()
            );
        }

        // Set shipping address if different from billing
        if ($customer->hasShippingAddress() && $customer->getShippingStreetAddress() !== null) {
            $this->shippingAddress(
                streetAddress: $customer->getShippingStreetAddress(),
                city: $customer->getShippingCity() ?? '',
                zipCode: $customer->getShippingPostalCode() ?? '',
                state: $customer->getShippingState(),
                country: $customer->getShippingCountry()
            );
        }

        // Use existing gateway customer ID if available
        if ($customer->getGatewayCustomerId() !== null) {
            $this->clientId($customer->getGatewayCustomerId());
        }

        return $this;
    }

    /**
     * Set customer email
     */
    public function email(string $email): self
    {
        $this->data['client']['email'] = $email;

        return $this;
    }

    /**
     * Set customer details
     */
    public function customer(
        string $email,
        ?string $fullName = null,
        ?string $phone = null,
        ?string $country = null
    ): self {
        $this->data['client']['email'] = $email;

        if ($fullName !== null) {
            $this->data['client']['full_name'] = $fullName;
        }

        if ($phone !== null) {
            $this->data['client']['phone'] = $phone;
        }

        if ($country !== null) {
            $this->data['client']['country'] = $country;
        }

        return $this;
    }

    /**
     * Set existing client ID
     */
    public function clientId(string $clientId): self
    {
        $this->data['client_id'] = $clientId;
        unset($this->data['client']);

        return $this;
    }

    /**
     * Set billing address
     */
    public function billingAddress(
        string $streetAddress,
        string $city,
        string $zipCode,
        ?string $state = null,
        ?string $country = null
    ): self {
        $this->data['client']['street_address'] = $streetAddress;
        $this->data['client']['city'] = $city;
        $this->data['client']['zip_code'] = $zipCode;

        if ($state !== null) {
            $this->data['client']['state'] = $state;
        }

        if ($country !== null) {
            $this->data['client']['country'] = $country;
        }

        return $this;
    }

    /**
     * Set shipping address
     */
    public function shippingAddress(
        string $streetAddress,
        string $city,
        string $zipCode,
        ?string $state = null,
        ?string $country = null
    ): self {
        $this->data['client']['shipping_street_address'] = $streetAddress;
        $this->data['client']['shipping_city'] = $city;
        $this->data['client']['shipping_zip_code'] = $zipCode;

        if ($state !== null) {
            $this->data['client']['shipping_state'] = $state;
        }

        if ($country !== null) {
            $this->data['client']['shipping_country'] = $country;
        }

        return $this;
    }

    /**
     * Set merchant reference
     */
    public function reference(string $reference): self
    {
        $this->data['reference'] = $reference;

        return $this;
    }

    /**
     * Set the key used to replay a previously-created purchase.
     */
    public function idempotencyKey(string $idempotencyKey): self
    {
        $idempotencyKey = mb_trim($idempotencyKey);

        if ($idempotencyKey === '') {
            throw new ChipValidationException('Idempotency key cannot be empty.');
        }

        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }

    /**
     * Set success redirect URL
     */
    public function successUrl(string $url): self
    {
        $this->data['success_redirect'] = $url;

        return $this;
    }

    /**
     * Set failure redirect URL
     */
    public function failureUrl(string $url): self
    {
        $this->data['failure_redirect'] = $url;

        return $this;
    }

    /**
     * Set cancel redirect URL
     */
    public function cancelUrl(string $url): self
    {
        $this->data['cancel_redirect'] = $url;

        return $this;
    }

    /**
     * Set all redirect URLs at once
     */
    public function redirects(string $successUrl, ?string $failureUrl = null, ?string $cancelUrl = null): self
    {
        $this->successUrl($successUrl);

        if ($failureUrl !== null) {
            $this->failureUrl($failureUrl);
        }

        if ($cancelUrl !== null) {
            $this->cancelUrl($cancelUrl);
        }

        return $this;
    }

    /**
     * Set webhook callback URL
     */
    public function webhook(string $url): self
    {
        $this->data['success_callback'] = $url;

        return $this;
    }

    /**
     * Enable email receipt
     */
    public function sendReceipt(bool $send = true): self
    {
        $this->data['send_receipt'] = $send;

        return $this;
    }

    /**
     * Enable pre-authorization (skip capture)
     */
    public function preAuthorize(bool $skipCapture = true): self
    {
        $this->data['skip_capture'] = $skipCapture;

        return $this;
    }

    /**
     * Force recurring token creation
     */
    public function forceRecurring(bool $force = true): self
    {
        $this->data['force_recurring'] = $force;

        return $this;
    }

    /**
     * Set due date
     */
    public function due(int $timestamp, bool $strict = false): self
    {
        $this->data['due'] = $timestamp;
        $this->data['purchase']['due_strict'] = $strict;

        return $this;
    }

    /**
     * Add notes to the purchase
     */
    public function notes(string $notes): self
    {
        $this->data['purchase']['notes'] = $notes;

        return $this;
    }

    /**
     * Set a discount override for the entire purchase.
     *
     * This applies a cart-level discount that reduces the total amount charged.
     * The discount is applied after product prices are summed.
     *
     * @param  int  $amount  Discount amount in cents (e.g., 5000 for RM 50.00)
     */
    public function discount(int $amount): self
    {
        if ($amount > 0) {
            $this->data['purchase']['total_discount_override'] = $amount;
        }

        return $this;
    }

    /**
     * Set metadata for the purchase.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): self
    {
        $this->data['purchase']['metadata'] = $metadata;

        return $this;
    }

    /**
     * Get the built data array (for inspection)
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Create the purchase
     */
    public function create(): PurchaseData
    {
        $this->purchaseCurrency();

        // Use brand_id from config if not set
        if (! isset($this->data['brand_id'])) {
            $this->data['brand_id'] = config('chip.collect.brand_id');
        }

        $data = $this->data;

        if ($this->idempotencyKey !== null) {
            $data['idempotency_key'] = $this->idempotencyKey;
        }

        return $this->service->createPurchase($data);
    }

    /**
     * Alias for create()
     */
    public function save(): PurchaseData
    {
        return $this->create();
    }

    private function purchaseCurrency(): string
    {
        $currency = $this->data['purchase']['currency'] ?? null;

        if (! is_string($currency) || $currency === '') {
            throw new ChipValidationException('Call currency() before adding purchase products.');
        }

        return $currency;
    }

    private function normalizeQuantity(string | float | int $quantity): int
    {
        if (is_float($quantity) && (! is_finite($quantity) || floor($quantity) !== $quantity)) {
            throw new ChipValidationException('Product quantity must be an integer.');
        }

        if (is_int($quantity)) {
            $normalized = $quantity;
        } elseif (is_float($quantity)) {
            $normalized = (int) $quantity;
        } else {
            $value = mb_trim($quantity);
            $validated = filter_var($value, FILTER_VALIDATE_INT);

            if ($value === '' || $validated === false) {
                throw new ChipValidationException('Product quantity must be an integer.');
            }

            $normalized = (int) $validated;
        }

        if ($normalized < 1) {
            throw new ChipValidationException('Product quantity must be an integer.');
        }

        return $normalized;
    }

    private function assertMoneyCurrency(Money $money, string $currency, string $field): void
    {
        if ($money->getCurrency()->getCurrency() !== $currency) {
            throw new ChipValidationException("{$field} currency must match the checkout currency.", [
                'checkout_currency' => $currency,
                'money_currency' => $money->getCurrency()->getCurrency(),
            ]);
        }
    }
}
