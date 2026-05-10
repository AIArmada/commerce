<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentCashier\Fixtures;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CheckoutBuilderContract;
use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Contracts\CustomerContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\InvoiceContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Contracts\PaymentMethodContract;
use AIArmada\Cashier\Contracts\SubscriptionBuilderContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\CashierChip\Subscription;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class ChipBillableUser extends User implements BillableContract
{
    public function gateway(?string $gateway = null): GatewayContract
    {
        return Cashier::gateway($gateway);
    }

    public function preferredGateway(): string
    {
        /** @var string|null $preferredGateway */
        $preferredGateway = $this->getAttribute('preferred_gateway');

        return $preferredGateway ?? 'chip';
    }

    public function setPreferredGateway(string $gateway): static
    {
        $this->setAttribute('preferred_gateway', $gateway);

        return $this;
    }

    public function defaultGateway(): string
    {
        return $this->preferredGateway();
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

    public function createOrGetCustomer(?string $gateway = null, array $options = []): CustomerContract
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function updateCustomer(?string $gateway = null, array $options = []): CustomerContract
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function asCustomer(?string $gateway = null): CustomerContract
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function syncCustomer(?string $gateway = null, array $options = []): CustomerContract
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

    public function chargeWithGateway(int $amount, string $paymentMethod, ?string $gateway = null, array $options = []): PaymentContract
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function newGatewaySubscription(string $type, string | array $prices = [], ?string $gateway = null): SubscriptionBuilderContract
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function checkoutWithGateway(?string $gateway = null): CheckoutBuilderContract
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function allGatewaySubscriptions(): Collection
    {
        return collect();
    }

    public function gatewaySubscriptions(?string $gateway = null): Collection
    {
        return collect();
    }

    public function gatewaySubscription(string $type, ?string $gateway = null): ?SubscriptionContract
    {
        return null;
    }

    public function subscribedViaGateway(string $type = 'default', ?string $price = null, ?string $gateway = null): bool
    {
        return false;
    }

    public function allGatewayPaymentMethods(): Collection
    {
        return collect($this->nativeChipPaymentMethods());
    }

    public function gatewayPaymentMethods(?string $gateway = null, ?string $type = null): Collection
    {
        return $gateway === null || $gateway === 'chip' ? collect($this->nativeChipPaymentMethods()) : collect();
    }

    public function defaultGatewayPaymentMethod(?string $gateway = null): ?PaymentMethodContract
    {
        return null;
    }

    public function createGatewaySetupIntent(?string $gateway = null, array $options = []): mixed
    {
        return null;
    }

    public function allGatewayInvoices(array $parameters = []): Collection
    {
        return collect($this->nativeChipInvoices());
    }

    public function gatewayInvoices(?string $gateway = null, array $parameters = []): Collection
    {
        return $gateway === null || $gateway === 'chip' ? collect($this->nativeChipInvoices()) : collect();
    }

    public function gatewayBillingPortalUrl(string $returnUrl, ?string $gateway = null, array $options = []): ?string
    {
        return null;
    }

    public function allSubscriptions(): Collection
    {
        return collect();
    }

    public function findSubscription(string $type = 'default'): ?SubscriptionContract
    {
        return null;
    }

    public function subscribedOnAny(string $type = 'default', ?string $price = null): bool
    {
        return false;
    }

    public function onTrialOnAny(string $type = 'default'): bool
    {
        return false;
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

    public function paymentMethods(?string $type = null): Collection
    {
        return collect($this->nativeChipPaymentMethods());
    }

    public function findPaymentMethod(string $paymentMethodId): mixed
    {
        return collect($this->nativeChipPaymentMethods())->firstWhere('id', $paymentMethodId);
    }

    public function hasDefaultPaymentMethod(): bool
    {
        return $this->defaultPaymentMethod() !== null;
    }

    public function hasPaymentMethod(?string $type = null): bool
    {
        return collect($this->nativeChipPaymentMethods())->isNotEmpty();
    }

    public function defaultPaymentMethod(): mixed
    {
        return collect($this->nativeChipPaymentMethods())->firstWhere('is_default', true);
    }

    public function updateDefaultPaymentMethod(string $paymentMethodId): self
    {
        $this->updateDefaultChipPaymentMethod($paymentMethodId);

        return $this;
    }

    public function deletePaymentMethod(string $paymentMethodId): void
    {
        $this->deleteChipPaymentMethod($paymentMethodId);
    }

    public function deletePaymentMethods(): void {}

    public function charge(int $amount, ?string $paymentMethod = null, array $options = []): mixed
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

    public function invoices(bool | array $parameters = false): Collection
    {
        return collect($this->nativeChipInvoices());
    }

    public function findInvoice(string $invoiceId): ?InvoiceContract
    {
        return null;
    }

    public function upcomingInvoice(array $options = []): ?InvoiceContract
    {
        return null;
    }

    public function stripeId(): ?string
    {
        return null;
    }

    public function asStripeCustomer(array $expand = []): mixed
    {
        return null;
    }

    public function createOrGetStripeCustomer(array $options = [], array $requestOptions = []): mixed
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

    public function billingPortalUrl(?string $returnUrl = null, array $options = []): string
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function nativeChipPaymentMethods(): array
    {
        return [
            [
                'id' => 'chip_pm_1',
                'recurring_token' => 'chip_pm_1',
                'payment_method' => 'Card',
                'card_brand' => 'Visa',
                'card_last4' => '1111',
                'card_expiry' => '12/30',
                'is_default' => true,
            ],
            [
                'id' => 'chip_pm_2',
                'recurring_token' => 'chip_pm_2',
                'payment_method' => 'Card',
                'card_brand' => 'Mastercard',
                'card_last4' => '2222',
                'card_expiry' => '01/31',
                'is_default' => false,
            ],
        ];
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
     * @return array<int, PurchaseData>
     */
    public function nativeChipInvoices(int $limit = 3): array
    {
        return array_slice([
            PurchaseData::from([
                'id' => 'chip_inv_2',
                'status' => 'paid',
                'created_on' => strtotime('2025-01-02 00:00:00'),
                'purchase' => [
                    'currency' => 'MYR',
                    'total' => 1299,
                    'products' => [[
                        'name' => 'Invoice 2',
                        'price' => 1299,
                    ]],
                ],
            ]),
            PurchaseData::from([
                'id' => 'chip_inv_1',
                'status' => 'created',
                'created_on' => strtotime('2025-01-01 00:00:00'),
                'purchase' => [
                    'currency' => 'MYR',
                    'total' => 3999,
                    'products' => [[
                        'name' => 'Invoice 1',
                        'price' => 3999,
                    ]],
                ],
            ]),
        ], 0, $limit);
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
