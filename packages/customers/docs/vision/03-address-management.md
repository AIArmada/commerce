# Address Management

> **Document:** 03 of 06  
> **Package:** `aiarmada/customers`  
> **Status:** Vision

---

## Overview

This document details the address book system, including the Address model, default address handling, and address validation.

---

## Address Model

```php
namespace AIArmada\Customers\Models;

class Address extends Model
{
    protected $fillable = [
        'customer_id',
        'label',                // "Home", "Office", etc.
        'first_name',
        'last_name',
        'company',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'phone',
        'is_default_billing',
        'is_default_shipping',
    ];

    protected $casts = [
        'is_default_billing' => 'boolean',
        'is_default_shipping' => 'boolean',
    ];

    // Relationships
    public function customer(): BelongsTo;

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getFormattedAddressAttribute(): string
    {
        return collect([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->getCountryName(),
        ])->filter()->implode(', ');
    }

    public function getCountryName(): string
    {
        return Countries::getName($this->country_code);
    }

    // Helpers
    public function toOrderAddress(): array
    {
        return $this->only([
            'first_name', 'last_name', 'company',
            'address_line_1', 'address_line_2',
            'city', 'state', 'postal_code', 'country_code', 'phone',
        ]);
    }
}
```

---

## Default Address Management

```php
class AddressService
{
    public function setDefaultBilling(Address $address): void
    {
        // Clear existing default
        $address->customer->addresses()
            ->where('is_default_billing', true)
            ->update(['is_default_billing' => false]);

        // Set new default
        $address->update(['is_default_billing' => true]);
    }

    public function setDefaultShipping(Address $address): void
    {
        // Clear existing default
        $address->customer->addresses()
            ->where('is_default_shipping', true)
            ->update(['is_default_shipping' => false]);

        // Set new default
        $address->update(['is_default_shipping' => true]);
    }

    public function getDefaultBilling(Customer $customer): ?Address
    {
        return $customer->addresses()
            ->where('is_default_billing', true)
            ->first() ?? $customer->addresses()->first();
    }

    public function getDefaultShipping(Customer $customer): ?Address
    {
        return $customer->addresses()
            ->where('is_default_shipping', true)
            ->first() ?? $this->getDefaultBilling($customer);
    }
}
```

---

## Address Validation

```php
class AddressValidator
{
    public function validate(array $data): array
    {
        return Validator::make($data, [
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'company' => 'nullable|string|max:150',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country_code' => 'required|string|size:2',
            'phone' => 'nullable|string|max:50',
        ])->validate();
    }

    public function validateForCountry(array $data, string $countryCode): array
    {
        $rules = $this->getCountryRules($countryCode);
        return Validator::make($data, $rules)->validate();
    }

    protected function getCountryRules(string $countryCode): array
    {
        return match ($countryCode) {
            'MY' => [
                'state' => 'required|in:' . implode(',', MalaysiaStates::all()),
                'postal_code' => 'required|digits:5',
            ],
            'SG' => [
                'postal_code' => 'required|digits:6',
            ],
            default => [],
        };
    }
}
```

---

## Address Display Component

```php
// Blade component for address display
@props(['address'])

<div {{ $attributes->merge(['class' => 'address-card']) }}>
    <div class="font-medium">{{ $address->full_name }}</div>
    @if($address->company)
        <div class="text-gray-600">{{ $address->company }}</div>
    @endif
    <div>{{ $address->address_line_1 }}</div>
    @if($address->address_line_2)
        <div>{{ $address->address_line_2 }}</div>
    @endif
    <div>{{ $address->city }}, {{ $address->state }} {{ $address->postal_code }}</div>
    <div>{{ $address->country_name }}</div>
    @if($address->phone)
        <div class="mt-2 text-gray-600">📞 {{ $address->phone }}</div>
    @endif
</div>
```

---

## Navigation

**Previous:** [02-customer-profiles.md](02-customer-profiles.md)  
**Next:** [04-segments-groups.md](04-segments-groups.md)
