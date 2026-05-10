<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Cashier\Fixtures;

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
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

final class OwnerScopedBillableUser extends Authenticatable implements BillableContract
{
    use HasOwner;
    use HasOwnerScopeConfig;

    protected static string $ownerScopeConfigKey = 'cashier.tests.owner';

    protected static bool $ownerScopeEnabledByDefault = true;

    protected $guarded = [];

    protected $table = 'users';

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    public function gateway(?string $gateway = null): GatewayContract
    {
        return Cashier::gateway($gateway);
    }

    public function preferredGateway(): string
    {
        /** @var string|null $preferredGateway */
        $preferredGateway = $this->getAttribute('preferred_gateway');

        return $preferredGateway ?? config('cashier.default', 'stripe');
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
        /** @var string|null $name */
        $name = $this->getAttribute('name');

        return $name;
    }

    public function customerEmail(): ?string
    {
        /** @var string|null $email */
        $email = $this->getAttribute('email');

        return $email;
    }

    public function customerPhone(): ?string
    {
        /** @var string|null $phone */
        $phone = $this->getAttribute('phone');

        return $phone;
    }

    /**
     * @return array<string, mixed>
     */
    public function customerAddress(): array
    {
        return [];
    }

    public function preferredCurrency(): string
    {
        return config('cashier.currency', 'USD');
    }

    public function preferredLocale(): ?string
    {
        return config('cashier.locale');
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
        return collect();
    }

    public function gatewayPaymentMethods(?string $gateway = null, ?string $type = null): Collection
    {
        return collect();
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
        return collect();
    }

    public function gatewayInvoices(?string $gateway = null, array $parameters = []): Collection
    {
        return collect();
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
        return $this->onTrial($type);
    }

    public function newSubscription(string $type, string | array $prices = [], ?string $gateway = null): SubscriptionBuilderContract
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function onTrial(string $type = 'default', ?string $price = null): bool
    {
        $trialEndsAt = $this->trialEndsAt();

        return $trialEndsAt !== null && $trialEndsAt->isFuture();
    }

    public function hasExpiredTrial(string $type = 'default', ?string $price = null): bool
    {
        $trialEndsAt = $this->trialEndsAt();

        return $trialEndsAt !== null && $trialEndsAt->isPast();
    }

    public function onGenericTrial(): bool
    {
        return $this->onTrial();
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
        return $this->hasMany(self::class, 'id');
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
        return collect();
    }

    public function findPaymentMethod(string $paymentMethodId): mixed
    {
        return null;
    }

    public function hasDefaultPaymentMethod(): bool
    {
        return false;
    }

    public function hasPaymentMethod(?string $type = null): bool
    {
        return false;
    }

    public function defaultPaymentMethod(): mixed
    {
        return null;
    }

    public function updateDefaultPaymentMethod(string $paymentMethodId): self
    {
        return $this;
    }

    public function deletePaymentMethod(string $paymentMethodId): void
    {
        // no-op for tests
    }

    public function deletePaymentMethods(): void
    {
        // no-op for tests
    }

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
        throw new RuntimeException('Not implemented for tests.');
    }

    public function invoices(bool | array $parameters = false): Collection
    {
        return collect();
    }

    public function findInvoice(string $invoiceId): ?InvoiceContract
    {
        return null;
    }

    public function upcomingInvoice(array $options = []): ?InvoiceContract
    {
        return null;
    }

    public function asStripeCustomer(array $expand = []): mixed
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function createOrGetStripeCustomer(array $options = [], array $requestOptions = []): mixed
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function updateStripeCustomer(array $options = []): mixed
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function syncStripeCustomerDetails(array $options = []): mixed
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function stripeId(): ?string
    {
        /** @var string|null $stripeId */
        $stripeId = $this->getAttribute('stripe_id');

        return $stripeId;
    }

    public function hasStripeId(): bool
    {
        return $this->stripeId() !== null;
    }

    public function createAsChipCustomer(array $options = []): mixed
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function createOrGetChipCustomer(array $options = []): mixed
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function updateChipCustomer(array $options = []): mixed
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function chipId(): ?string
    {
        /** @var string|null $chipId */
        $chipId = $this->getAttribute('chip_id');

        return $chipId;
    }

    public function hasChipId(): bool
    {
        return $this->chipId() !== null;
    }

    public function trialEndsAt(): ?Carbon
    {
        /** @var Carbon|null $trialEndsAt */
        $trialEndsAt = $this->getAttribute('trial_ends_at');

        return $trialEndsAt;
    }

    public function createSetupIntent(array $options = []): mixed
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function billingPortalUrl(?string $returnUrl = null, array $options = []): string
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function syncChipCustomerDetails(): mixed
    {
        throw new RuntimeException('Not implemented for tests.');
    }

    public function createSetupPurchase(array $options = []): mixed
    {
        throw new RuntimeException('Not implemented for tests.');
    }
}
