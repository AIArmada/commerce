<?php

declare(strict_types=1);

namespace AIArmada\Chip\Builders;

use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Enums\RequestClientDetail;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Support\TaxPercent;
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

    /**
     * Payment method names CHIP accepts in `payment_method_whitelist`,
     * as returned by `GET /payment_methods/`. Deliberately hardcoded:
     * `EWallet` covers a different (direct-post) key space.
     *
     * @var list<string>
     */
    private const array PAYMENT_METHOD_WHITELIST = [
        'fpx',
        'fpx_b2b1',
        'crypto_coin',
        'dnqr',
        'duitnow_qr',
        'maestro',
        'mastercard',
        'mpgs_apple_pay',
        'mpgs_google_pay',
        'razer_atome',
        'razer_grabpay',
        'razer_maybankqr',
        'razer_shopeepay',
        'razer_tng',
        'shopee_pay',
        'visa',
    ];

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
        float | string $taxPercent = 0,
        ?string $category = null,
        ?int $totalPriceOverride = null
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

        $taxPercent = TaxPercent::normalize($taxPercent);

        if ($taxPercent > 0) {
            $product['tax_percent'] = $taxPercent;
        }

        if ($category !== null) {
            $product['category'] = $category;
        }

        if ($totalPriceOverride !== null) {
            if ($totalPriceOverride < 0) {
                throw new ChipValidationException('Product total price override cannot be negative.');
            }

            $product['total_price_override'] = $totalPriceOverride;
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

        $productData = $product->toRequestArray();
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
        float | string $taxPercent = 0,
        ?string $category = null,
        ?int $totalPriceOverride = null
    ): self {
        $currency = $this->purchaseCurrency();

        return $this->addProductMoney(
            name: $name,
            price: Money::{$currency}($priceInCents),
            quantity: $quantity,
            discount: $discountInCents > 0 ? Money::{$currency}($discountInCents) : null,
            taxPercent: $taxPercent,
            category: $category,
            totalPriceOverride: $totalPriceOverride
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

            $lineItemsSubtotal += ProductData::multiplyMinorUnits((int) $price->getAmount(), (string) $quantity);
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
        // Gateway-linked customers resolve to the linked CHIP Client record,
        // so skip the client build entirely: its output would be discarded
        // by clientId() below.
        $gatewayCustomerId = $customer->getGatewayCustomerId();

        if ($gatewayCustomerId !== null) {
            return $this->clientId($gatewayCustomerId);
        }

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

        return $this;
    }

    /**
     * Set customer email
     */
    public function email(string $email): self
    {
        unset($this->data['client_id']);

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
        unset($this->data['client_id']);

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
        unset($this->data['client_id']);

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
        unset($this->data['client_id']);

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
     * Set a display-only discount override for the entire purchase.
     *
     * Writes `purchase.total_discount_override`, which is visual-only:
     * CHIP calculates `total` from `products`, so this field does not
     * change the amount charged. A lone discount neither throws nor
     * reduces anything — the local total checks only run when
     * `total_override` is also present, and `total_override` without
     * the other three overrides throws. Only set this as part of a
     * reconciling four-override set
     * (`subtotal - discount + tax === total`). Amounts of zero or
     * less write nothing.
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
     * Set the invoice-issued date (display-only; CHIP generates the
     * current date when omitted).
     */
    public function issued(string $date): self
    {
        if (mb_trim($date) === '') {
            throw new ChipValidationException('Issued date cannot be blank.');
        }

        $this->data['issued'] = $date;

        return $this;
    }

    /**
     * Restrict the purchase to specific payment methods.
     *
     * @param  list<string>  $methods
     */
    public function paymentMethodWhitelist(array $methods): self
    {
        if ($methods === []) {
            throw new ChipValidationException('Payment method whitelist cannot be empty.');
        }

        foreach ($methods as $method) {
            if (! is_string($method) || ! in_array($method, self::PAYMENT_METHOD_WHITELIST, true)) {
                throw new ChipValidationException('Unknown payment method in whitelist.', ['method' => $method]);
            }
        }

        $this->data['payment_method_whitelist'] = array_values($methods);

        return $this;
    }

    /**
     * Tag the purchase.
     *
     * @param  list<string>  $tags
     */
    public function tags(array $tags): self
    {
        foreach ($tags as $tag) {
            if (! is_string($tag) || mb_trim($tag) === '') {
                throw new ChipValidationException('Purchase tags must be non-blank strings.');
            }
        }

        $this->data['tags'] = array_values($tags);

        return $this;
    }

    /**
     * Set the invoice/payment-form language (ISO 639-1).
     */
    public function language(string $language): self
    {
        $this->data['purchase']['language'] = $language;

        return $this;
    }

    /**
     * Set an invoice-total adjustment in cents. May be negative
     * (subtracted from the total).
     */
    public function debt(int $cents): self
    {
        $this->data['purchase']['debt'] = $cents;

        return $this;
    }

    /**
     * Set the timezone for invoice-specific timestamps.
     */
    public function timezone(string $timezone): self
    {
        if (mb_trim($timezone) === '') {
            throw new ChipValidationException('Timezone cannot be blank.');
        }

        $this->data['purchase']['timezone'] = $timezone;

        return $this;
    }

    /**
     * Set the message included with the invoice email.
     */
    public function emailMessage(string $message): self
    {
        $this->data['purchase']['email_message'] = $message;

        return $this;
    }

    /**
     * Request client details from the payer before payment.
     *
     * @param  list<RequestClientDetail|string>  $fields
     */
    public function requestClientDetails(array $fields): self
    {
        $values = [];

        foreach ($fields as $field) {
            $value = $field instanceof RequestClientDetail ? $field->value : $field;

            if (! is_string($value) || RequestClientDetail::tryFrom($value) === null) {
                throw new ChipValidationException('Unknown request client details field.', ['field' => $field]);
            }

            $values[] = $value;
        }

        if (count($values) !== count(array_unique($values))) {
            throw new ChipValidationException('Request client details must not contain duplicates.');
        }

        $this->data['purchase']['request_client_details'] = $values;

        return $this;
    }

    /**
     * Carbon-copy notification emails.
     *
     * @param  list<string>  $emails
     */
    public function cc(array $emails): self
    {
        $normalized = $this->normalizeEmailList($emails, 'cc');

        unset($this->data['client_id']);

        $this->data['client']['cc'] = $normalized;

        return $this;
    }

    /**
     * Blind-carbon-copy notification emails.
     *
     * @param  list<string>  $emails
     */
    public function bcc(array $emails): self
    {
        $normalized = $this->normalizeEmailList($emails, 'bcc');

        unset($this->data['client_id']);

        $this->data['client']['bcc'] = $normalized;

        return $this;
    }

    /**
     * Set the client company legal name.
     */
    public function legalName(string $legalName): self
    {
        if (mb_trim($legalName) === '') {
            throw new ChipValidationException('Client legal name cannot be blank.');
        }

        unset($this->data['client_id']);

        $this->data['client']['legal_name'] = $legalName;

        return $this;
    }

    /**
     * Set the client company brand name.
     */
    public function brandName(string $brandName): self
    {
        if (mb_trim($brandName) === '') {
            throw new ChipValidationException('Client brand name cannot be blank.');
        }

        unset($this->data['client_id']);

        $this->data['client']['brand_name'] = $brandName;

        return $this;
    }

    /**
     * Set the client company registration number.
     */
    public function registrationNumber(string $registrationNumber): self
    {
        if (mb_trim($registrationNumber) === '') {
            throw new ChipValidationException('Client registration number cannot be blank.');
        }

        unset($this->data['client_id']);

        $this->data['client']['registration_number'] = $registrationNumber;

        return $this;
    }

    /**
     * Set the client tax number.
     */
    public function taxNumber(string $taxNumber): self
    {
        if (mb_trim($taxNumber) === '') {
            throw new ChipValidationException('Client tax number cannot be blank.');
        }

        unset($this->data['client_id']);

        $this->data['client']['tax_number'] = $taxNumber;

        return $this;
    }

    /**
     * Set the client bank account number.
     */
    public function bankAccount(string $bankAccount): self
    {
        if (mb_trim($bankAccount) === '') {
            throw new ChipValidationException('Client bank account cannot be blank.');
        }

        unset($this->data['client_id']);

        $this->data['client']['bank_account'] = $bankAccount;

        return $this;
    }

    /**
     * Set the client bank SWIFT/BIC code.
     */
    public function bankCode(string $bankCode): self
    {
        if (mb_trim($bankCode) === '') {
            throw new ChipValidationException('Client bank code cannot be blank.');
        }

        unset($this->data['client_id']);

        $this->data['client']['bank_code'] = $bankCode;

        return $this;
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    private function normalizeEmailList(array $emails, string $field): array
    {
        foreach ($emails as $email) {
            if (! is_string($email) || mb_trim($email) === '') {
                throw new ChipValidationException("Client {$field} entries must be non-blank strings.");
            }
        }

        return array_values(array_map(
            fn (string $email): string => mb_trim($email),
            $emails
        ));
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

        return $this->service->createPurchase($this->data, $this->idempotencyKey);
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

    private function normalizeQuantity(string | float | int $quantity): int | string
    {
        if (is_int($quantity)) {
            $normalized = $quantity;
        } elseif (is_float($quantity)) {
            if (! is_finite($quantity)) {
                throw new ChipValidationException('Product quantity must be numeric.');
            }

            // 2**53 is the float64-exactness boundary; larger whole floats
            // would silently narrow on (int) cast, so they stay strings
            // for the range check below.
            $normalized = floor($quantity) === $quantity && $quantity < 2 ** 53 ? (int) $quantity : (string) $quantity;
        } else {
            $value = mb_trim($quantity);

            if ($value === '' || ! is_numeric($value) || ! is_finite((float) $value)) {
                throw new ChipValidationException('Product quantity must be numeric.');
            }

            $normalized = $value;
        }

        $asFloat = (float) $normalized;

        if ($asFloat < 0) {
            throw new ChipValidationException('Product quantity must be zero or greater.');
        }

        if ($asFloat >= 2 ** 53) {
            throw new ChipValidationException('Product quantity is out of range.');
        }

        if ($this->decimalPlaces((string) $normalized) > 4) {
            throw new ChipValidationException('Product quantity must have at most 4 decimal places.');
        }

        return $normalized;
    }

    /**
     * Count decimal places in a numeric string, mirroring how a decimal
     * field parses it: fraction digits minus the exponent, floored at 0.
     * String-counted (not float-rounded) so precision-collapsed forms
     * like "1.0000000000000001" cannot slip through a float cast.
     */
    private function decimalPlaces(string $value): int
    {
        $value = mb_ltrim($value, '+-');
        $exponent = 0;

        $ePos = mb_stripos($value, 'e');

        if ($ePos !== false) {
            $exponent = (int) mb_substr($value, $ePos + 1);
            $value = mb_substr($value, 0, $ePos);
        }

        $dotPos = mb_strpos($value, '.');
        $fractionDigits = $dotPos === false ? 0 : mb_strlen($value) - $dotPos - 1;

        return max(0, $fractionDigits - $exponent);
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
