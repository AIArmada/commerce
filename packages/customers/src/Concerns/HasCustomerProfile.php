<?php

declare(strict_types=1);

namespace AIArmada\Customers\Concerns;

use AIArmada\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Trait to be used on User models to provide customer profile functionality.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasCustomerProfile
{
    /**
     * Get the customer profile for this user.
     */
    public function customerProfile(): HasOne
    {
        return $this->hasOne(Customer::class, 'user_id');
    }

    /**
     * Get or create the customer profile for this user.
     */
    public function getOrCreateCustomerProfile(): Customer
    {
        $customer = $this->customerProfile;

        if ($customer) {
            return $customer;
        }

        return $this->customerProfile()->create([
            'first_name' => $this->name ?? explode(' ', $this->name ?? 'User')[0],
            'last_name' => count(explode(' ', $this->name ?? '')) > 1
                ? last(explode(' ', $this->name ?? ''))
                : '',
            'email' => $this->email,
            'phone' => $this->phone ?? null,
        ]);
    }

    /**
     * Check if user has a customer profile.
     */
    public function hasCustomerProfile(): bool
    {
        return $this->customerProfile()->exists();
    }

    /**
     * Get the customer's wallet balance.
     */
    public function getWalletBalance(): int
    {
        return $this->customerProfile?->wallet_balance ?? 0;
    }

    /**
     * Get the customer's lifetime value.
     */
    public function getLifetimeValue(): int
    {
        return $this->customerProfile?->lifetime_value ?? 0;
    }

    /**
     * Check if customer accepts marketing.
     */
    public function acceptsMarketing(): bool
    {
        return $this->customerProfile?->accepts_marketing ?? false;
    }

    /**
     * Get the customer's default shipping address.
     */
    public function getDefaultShippingAddress()
    {
        return $this->customerProfile?->getDefaultShippingAddress();
    }

    /**
     * Get the customer's default billing address.
     */
    public function getDefaultBillingAddress()
    {
        return $this->customerProfile?->getDefaultBillingAddress();
    }
}
