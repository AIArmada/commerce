<?php

declare(strict_types=1);

namespace Stripe {
    final class StripeObject
    {
        /**
         * @return array<string, mixed>
         */
        public function toArray(): array
        {
            return [];
        }
    }

    final class StripeClient
    {
        public mixed $checkout;

        public mixed $charges;

        public mixed $refunds;

        public mixed $subscriptions;

        public mixed $paymentIntents;

        public mixed $invoices;

        public mixed $billingPortal;

        public mixed $prices;

        public mixed $products;

        public function __construct(?string $apiKey = null, mixed $config = null) {}
    }

    final class Account
    {
        public string $id;

        public static function retrieve(mixed $id = null, mixed $opts = null): self
        {
            return new self;
        }
    }

    final class Webhook
    {
        public static function constructEvent(string $payload, string $sigHeader, string $secret): object
        {
            return (object) [];
        }
    }

    final class Customer
    {
        public string $id;

        public ?string $email = null;

        public ?string $name = null;

        public ?string $phone = null;

        public ?object $address = null;

        public ?StripeObject $metadata = null;
    }

    final class Invoice
    {
        public string $id;

        public ?string $number = null;

        public int | string | null $created = null;

        public int | string | null $due_date = null;

        public ?string $status = null;

        public int | string | null $total = null;

        public int | string | null $subtotal = null;

        public int | string | null $tax = null;

        public ?string $currency = null;

        public mixed $lines = null;

        public mixed $customer = null;

        public ?string $hosted_invoice_url = null;

        public ?string $invoice_pdf = null;
    }

    /**
     * @implements \ArrayAccess<array-key, mixed>
     */
    final class InvoiceLineItem implements \ArrayAccess
    {
        public string $id;

        public ?string $description = null;

        public ?int $quantity = null;

        public int | string | null $amount = null;

        public int | string | null $unit_amount_excluding_tax = null;

        public ?string $currency = null;

        public ?bool $proration = null;

        public function offsetExists(mixed $offset): bool
        {
            return false;
        }

        public function offsetGet(mixed $offset): mixed
        {
            return null;
        }

        public function offsetSet(mixed $offset, mixed $value): void {}

        public function offsetUnset(mixed $offset): void {}
    }

    final class Price
    {
        public string $id;
    }
}

namespace Stripe\Checkout {
    final class Session
    {
        public string $id;

        public ?string $url = null;

        public ?string $success_url = null;

        public ?string $cancel_url = null;

        public ?string $status = null;

        public ?string $payment_status = null;

        public int | string | null $amount_total = null;

        public ?string $currency = null;
    }
}
