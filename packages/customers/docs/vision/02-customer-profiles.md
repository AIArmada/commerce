# Customer Profiles & Models

> **Document:** 02 of 06  
> **Package:** `aiarmada/customers`  
> **Status:** Vision

---

## Overview

This document details the customer profile system, including the Customer model, user extension pattern, and profile data management.

---

## Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       CUSTOMER ENTITY RELATIONSHIPS                         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│                              ┌─────────────────┐                             │
│                              │      User       │                             │
│                              │   (Laravel)     │                             │
│                              └────────┬────────┘                             │
│                                       │ 1:1                                  │
│                                       ▼                                      │
│ ┌────────────────┐            ┌─────────────────┐         ┌────────────────┐│
│ │    Address     │ N:1        │    CUSTOMER     │    1:N  │   Wishlist     ││
│ │  (addresses)   │◀───────────│  (customers)    │────────▶│   (wishlists)  ││
│ └────────────────┘            └────────┬────────┘         └────────────────┘│
│                                        │                                     │
│        ┌──────────────┬────────────────┼────────────────┬──────────────┐    │
│        │              │                │                │              │    │
│        ▼              ▼                ▼                ▼              ▼    │
│ ┌────────────┐ ┌────────────┐ ┌─────────────┐ ┌────────────┐ ┌────────────┐ │
│ │   Order    │ │  Segment   │ │ Customer    │ │  Activity  │ │   Note     │ │
│ │  (orders)  │ │ (segments) │ │   Group     │ │   (logs)   │ │  (notes)   │ │
│ └────────────┘ └────────────┘ └─────────────┘ └────────────┘ └────────────┘ │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Customer Model

```php
namespace AIArmada\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use AIArmada\CommerceSupport\Traits\HasMoney;

class Customer extends Model
{
    use HasMoney;

    protected $fillable = [
        'user_id',              // Link to Laravel User (optional for guests)
        'email',
        'first_name',
        'last_name',
        'phone',
        'company',
        'tax_number',           // SST/VAT number for B2B
        'accepts_marketing',
        'locale',
        'currency',
        'notes',
        'metadata',             // Custom JSON data
        'last_order_at',
        'orders_count',
        'total_spent',
    ];

    protected $casts = [
        'accepts_marketing' => 'boolean',
        'metadata' => 'array',
        'last_order_at' => 'datetime',
        'orders_count' => 'integer',
        'total_spent' => 'integer',
    ];

    // Relationships
    public function user(): BelongsTo;
    public function addresses(): HasMany;
    public function defaultBillingAddress(): HasOne;
    public function defaultShippingAddress(): HasOne;
    public function orders(): HasMany;
    public function wishlists(): HasMany;
    public function segments(): BelongsToMany;
    public function groups(): BelongsToMany;
    public function activities(): HasMany;
    public function notes(): HasMany;

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getLifetimeValueAttribute(): Money
    {
        return Money::MYR($this->total_spent);
    }

    // Scopes
    public function scopeAcceptsMarketing($query);
    public function scopeActive($query);
    public function scopeInSegment($query, Segment $segment);

    // Helpers
    public function isGuest(): bool;
    public function hasOrders(): bool;
    public function canBeMerged(): bool;
}
```

---

## User Extension Pattern

The Customer package extends the base Laravel User model without modifying it:

```php
// In User model (app/Models/User.php)
use AIArmada\Customers\Traits\IsCustomer;

class User extends Authenticatable
{
    use IsCustomer;
}

// The trait provides:
trait IsCustomer
{
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function getOrCreateCustomer(): Customer
    {
        return $this->customer ?? $this->customer()->create([
            'email' => $this->email,
            'first_name' => $this->name,
        ]);
    }

    public function addresses(): HasManyThrough
    {
        return $this->hasManyThrough(Address::class, Customer::class);
    }
}
```

---

## Guest Customer Handling

Guest customers (not logged in) are tracked by email:

```php
class CustomerService
{
    public function findOrCreateByEmail(string $email, array $data = []): Customer
    {
        return Customer::firstOrCreate(
            ['email' => $email, 'user_id' => null],
            array_merge(['email' => $email], $data)
        );
    }

    public function mergeGuestToUser(Customer $guest, User $user): Customer
    {
        // If user already has a customer, merge orders
        if ($user->customer) {
            $this->transferOrders($guest, $user->customer);
            $this->transferAddresses($guest, $user->customer);
            $guest->delete();
            return $user->customer;
        }

        // Otherwise, assign guest customer to user
        $guest->update(['user_id' => $user->id]);
        return $guest;
    }
}
```

---

## Customer Statistics

Automatically calculated statistics:

```php
class CustomerStatisticsService
{
    public function recalculate(Customer $customer): void
    {
        $stats = $customer->orders()
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as orders_count, MAX(created_at) as last_order_at, SUM(grand_total) as total_spent')
            ->first();

        $customer->update([
            'orders_count' => $stats->orders_count ?? 0,
            'last_order_at' => $stats->last_order_at,
            'total_spent' => $stats->total_spent ?? 0,
        ]);
    }
}
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-address-management.md](03-address-management.md)
