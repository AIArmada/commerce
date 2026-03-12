<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentCashier\Fixtures;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Contracts\CustomerContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\InvoiceContract;
use AIArmada\Cashier\Contracts\SubscriptionBuilderContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\CashierChip\Subscription;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ChipBillableUser extends User implements BillableContract
{
    public function gateway(?string $gateway = null): GatewayContract
    {
        return Cashier::gateway($gateway);
    }

    public function defaultGateway(): string
    {
        return config('cashier.default', 'stripe');
    }

    public function gatewayId(?string $gateway = null): ?string
    {
        return null;
    }

    public function hasGatewayId(?string $gateway = null): bool
    {
        return false;
    }

    public function createAsCustomer(array $options = [], ?string $gateway = null): CustomerContract
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function createOrGetCustomer(array $options = [], ?string $gateway = null): CustomerContract
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function updateCustomer(array $options = [], ?string $gateway = null): CustomerContract
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function asCustomer(?string $gateway = null): CustomerContract
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function syncCustomerDetails(?string $gateway = null): self
    {
        return $this;
    }

    public function customerName(): ?string
    {
        return $this->name;
    }

    public function customerEmail(): ?string
    {
        return $this->email;
    }

    public function customerPhone(): ?string
    {
        return null;
    }

    public function customerAddress(): array
    {
        return [];
    }

    public function preferredCurrency(): string
    {
        return 'MYR';
    }

    public function preferredLocale(): ?string
    {
        return null;
    }

    public function newSubscription(string $type, string | array $prices = [], ?string $gateway = null): SubscriptionBuilderContract
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function onTrial(string $type = 'default', ?string $price = null): bool
    {
        return false;
    }

    public function hasExpiredTrial(string $type = 'default', ?string $price = null): bool
    {
        return false;
    }

    public function onGenericTrial(): bool
    {
        return false;
    }

    public function subscribed(string $type = 'default', ?string $price = null): bool
    {
        return false;
    }

    public function subscription(string $type = 'default'): ?SubscriptionContract
    {
        return null;
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'user_id');
    }

    public function hasIncompletePayment(string $type = 'default'): bool
    {
        return false;
    }

    public function subscribedToProduct(string | array $products, string $type = 'default'): bool
    {
        return false;
    }

    public function subscribedToPrice(string | array $prices, string $type = 'default'): bool
    {
        return false;
    }

    public function paymentMethods(?string $gateway = null): Collection
    {
        return $gateway === 'chip' ? $this->chipPaymentMethods() : collect();
    }

    public function findPaymentMethod(string $paymentMethodId, ?string $gateway = null): mixed
    {
        return null;
    }

    public function hasDefaultPaymentMethod(?string $gateway = null): bool
    {
        return false;
    }

    public function hasPaymentMethod(?string $gateway = null): bool
    {
        return false;
    }

    public function defaultPaymentMethod(?string $gateway = null): mixed
    {
        return null;
    }

    public function updateDefaultPaymentMethod(string $paymentMethodId, ?string $gateway = null): self
    {
        return $this;
    }

    public function deletePaymentMethod(string $paymentMethodId, ?string $gateway = null): void {}

    public function deletePaymentMethods(?string $gateway = null): void {}

    public function charge(int $amount, ?string $paymentMethod = null, array $options = [], ?string $gateway = null): mixed
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function checkout(string | array $items, array $sessionOptions = [], array $customerOptions = [], ?string $gateway = null): CheckoutContract
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function refund(string $paymentId, ?int $amount = null, ?string $gateway = null): mixed
    {
        return null;
    }

    public function invoices(bool $includePending = false, ?string $gateway = null): Collection
    {
        return $gateway === 'chip' ? collect($this->chipInvoices()) : collect();
    }

    public function findInvoice(string $invoiceId, ?string $gateway = null): ?InvoiceContract
    {
        return null;
    }

    public function upcomingInvoice(?string $gateway = null): ?InvoiceContract
    {
        return null;
    }

    public function stripeId(): ?string
    {
        return null;
    }

    public function asStripeCustomer(): mixed
    {
        return null;
    }

    public function createOrGetStripeCustomer(array $options = []): mixed
    {
        return null;
    }

    public function updateStripeCustomer(array $options = []): mixed
    {
        return null;
    }

    public function syncStripeCustomerDetails(array $options = []): mixed
    {
        return null;
    }

    public function createSetupIntent(array $options = []): mixed
    {
        return null;
    }

    public function billingPortalUrl(string $returnUrl, array $options = []): string
    {
        return '';
    }

    public function createOrGetChipCustomer(array $options = []): mixed
    {
        return null;
    }

    public function updateChipCustomer(array $options = []): mixed
    {
        return null;
    }

    public function syncChipCustomerDetails(): mixed
    {
        return null;
    }

    public function createSetupPurchase(array $options = []): mixed
    {
        return null;
    }

    public function chipPaymentMethods(): Collection
    {
        return collect([
            (object) [
                'id' => 'chip_pm_1',
                'type' => 'Card',
                'last4' => '1111',
                'expiry' => '12/30',
                'is_default' => true,
            ],
            (object) [
                'id' => 'chip_pm_2',
                'type' => 'Card',
                'last4' => '2222',
                'expiry' => '01/31',
                'is_default' => false,
            ],
        ]);
    }

    public function defaultChipPaymentMethod(): ?object
    {
        return $this->chipPaymentMethods()->first();
    }

    public function updateDefaultChipPaymentMethod(string $paymentMethodId): void
    {
        $this->attributes['default_chip_payment_method_id'] = $paymentMethodId;
    }

    public function deleteChipPaymentMethod(string $paymentMethodId): void
    {
        $this->attributes['deleted_chip_payment_method_id'] = $paymentMethodId;
    }

    /**
     * @return array<int, object>
     */
    public function chipInvoices(int $limit = 3): array
    {
        return array_slice([
            (object) [
                'id' => 'chip_inv_2',
                'number' => 'INV-0002',
                'amount' => 1299,
                'created_at' => Carbon::parse('2025-01-02 00:00:00'),
                'status' => 'paid',
                'pdf_url' => 'https://example.test/invoices/chip_inv_2.pdf',
            ],
            (object) [
                'id' => 'chip_inv_1',
                'number' => 'INV-0001',
                'amount' => 3999,
                'created_at' => Carbon::parse('2025-01-01 00:00:00'),
                'status' => 'open',
                'pdf_url' => null,
            ],
        ], 0, $limit);
    }
}
