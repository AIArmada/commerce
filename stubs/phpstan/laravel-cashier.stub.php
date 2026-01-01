<?php

declare(strict_types=1);

namespace Laravel\Cashier {
    final class Cashier
    {
        public static bool $deactivatePastDue = true;

        public static bool $deactivateIncomplete = true;

        public static function stripe(): \Stripe\StripeClient
        {
            return new \Stripe\StripeClient;
        }
    }

    final class Invoice
    {
        public function asStripeInvoice(): \Stripe\Invoice
        {
            return new \Stripe\Invoice;
        }

        public function total(): string
        {
            return '0.00';
        }

        public function subtotal(): string
        {
            return '0.00';
        }

        public function tax(): string
        {
            return '0.00';
        }

        /**
         * @param  array<string, mixed>  $data
         */
        public function download(array $data = []): mixed
        {
            return null;
        }

        /**
         * @param  array<string, mixed>  $data
         */
        public function view(array $data = []): mixed
        {
            return null;
        }
    }

    final class Payment
    {
        public function __construct(mixed $paymentIntent = null) {}

        public function asStripePaymentIntent(): object
        {
            return (object) [];
        }

        public function requiresAction(): bool
        {
            return false;
        }

        public function requiresPaymentMethod(): bool
        {
            return false;
        }

        public function isSucceeded(): bool
        {
            return true;
        }

        public function isCanceled(): bool
        {
            return false;
        }

        public function amount(): string
        {
            return '0.00';
        }

        public function rawAmount(): int
        {
            return 0;
        }

        public function validate(): void {}
    }

    final class PaymentMethod
    {
        public function asStripePaymentMethod(): object
        {
            return (object) [];
        }

        public function delete(): void {}
    }

    class Subscription extends \Illuminate\Database\Eloquent\Model
    {
        public string $id;

        public mixed $items = null;

        public mixed $owner = null;

        public function getAttribute(string $key): mixed
        {
            return null;
        }

        public function valid(): bool
        {
            return true;
        }

        public function active(): bool
        {
            return true;
        }

        public function onTrial(): bool
        {
            return false;
        }

        public function hasExpiredTrial(): bool
        {
            return false;
        }

        public function canceled(): bool
        {
            return false;
        }

        public function ended(): bool
        {
            return false;
        }

        public function onGracePeriod(): bool
        {
            return false;
        }

        public function recurring(): bool
        {
            return true;
        }

        public function pastDue(): bool
        {
            return false;
        }

        public function incomplete(): bool
        {
            return false;
        }

        public function hasIncompletePayment(): bool
        {
            return false;
        }

        public function hasPrice(string $price): bool
        {
            return false;
        }

        public function cancel(): void {}

        public function cancelNow(): void {}

        public function cancelNowAndInvoice(): void {}

        public function resume(): void {}

        /**
         * @param  array<string, mixed>  $options
         */
        public function swap(string $price, array $options = []): void {}

        public function updateQuantity(int $quantity, string $price = ''): void {}

        public function incrementQuantity(int $by = 1, string $price = ''): void {}

        public function decrementQuantity(int $by = 1, string $price = ''): void {}

        public function currentPeriodStart(): mixed
        {
            return null;
        }

        public function currentPeriodEnd(): mixed
        {
            return null;
        }
    }

    class SubscriptionItem extends \Illuminate\Database\Eloquent\Model
    {
        public function getKey(): mixed
        {
            return null;
        }

        public function getAttribute(string $key): mixed
        {
            return null;
        }

        public function currentPeriodStart(): mixed
        {
            return null;
        }

        public function currentPeriodEnd(): mixed
        {
            return null;
        }

        public function updateQuantity(int $quantity): void {}

        public function incrementQuantity(int $by = 1): void {}

        public function decrementQuantity(int $by = 1): void {}

        /**
         * @param  array<string, mixed>  $options
         */
        public function swap(string $price, array $options = []): void {}
    }

    final class Checkout
    {
        public function asStripeCheckoutSession(): \Stripe\Checkout\Session
        {
            return new \Stripe\Checkout\Session;
        }
    }

    final class SubscriptionBuilder
    {
        /**
         * @param  string|array<int, string>  $prices
         */
        public function __construct(mixed $owner = null, string $type = '', string | array $prices = []) {}

        public function allowPaymentFailures(): self
        {
            return $this;
        }

        public function price(string $price, ?int $quantity = 1): self
        {
            return $this;
        }

        public function quantity(?int $quantity, ?string $price = null): self
        {
            return $this;
        }

        public function trialDays(int $days): self
        {
            return $this;
        }

        public function trialUntil(mixed $date): self
        {
            return $this;
        }

        public function skipTrial(): self
        {
            return $this;
        }

        public function anchorBillingCycleOn(mixed $date): self
        {
            return $this;
        }

        public function withCoupon(string $coupon): self
        {
            return $this;
        }

        public function withPromotionCode(string $code): self
        {
            return $this;
        }

        /**
         * @param  array<string, mixed>  $metadata
         */
        public function withMetadata(array $metadata): self
        {
            return $this;
        }

        /**
         * @param  array<string, mixed>  $options
         */
        public function add(array $options = []): Subscription
        {
            return new Subscription;
        }

        /**
         * @param  array<string, mixed>  $options
         */
        public function create(?string $paymentMethod = null, array $options = []): Subscription
        {
            return new Subscription;
        }

        /**
         * @param  array<string, mixed>  $options
         */
        public function checkout(array $options = []): Checkout
        {
            return new Checkout;
        }
    }
}
