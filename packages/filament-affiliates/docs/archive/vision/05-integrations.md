# Filament Affiliates - Integrations

> **Document:** 05 of 06  
> **Package:** `aiarmada/filament-affiliates`  
> **Status:** ✅ Complete  
> **Last Updated:** December 2025

---

## Overview

This document outlines integrations between `filament-affiliates` and other commerce packages, enabling deep linking and cross-package navigation.

---

## Package Dependencies

```
┌────────────────────────────────────────────────────────────────┐
│                    filament-affiliates                          │
├────────────────────────────────────────────────────────────────┤
│                           │                                     │
│              ┌────────────┴────────────┐                       │
│              ▼                         ▼                       │
│    ┌─────────────────┐       ┌─────────────────┐              │
│    │    affiliates   │       │    filament     │              │
│    │   (core logic)  │       │   (UI framework)│              │
│    └─────────────────┘       └─────────────────┘              │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
                    │
        ┌───────────┼───────────┐
        ▼           ▼           ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│ filament-cart│ │filament-     │ │filament-     │
│   (optional) │ │vouchers      │ │orders        │
│              │ │(optional)    │ │(optional)    │
└──────────────┘ └──────────────┘ └──────────────┘
```

---

## CartBridge

Enables deep linking from affiliate resources to cart-related data.

### Purpose

When viewing an affiliate conversion, allow admins to navigate directly to the related cart or order in FilamentCart.

### Implementation

```php
namespace AIArmada\FilamentAffiliates\Support\Integrations;

final class CartBridge
{
    public function isAvailable(): bool
    {
        return class_exists(\AIArmada\FilamentCart\FilamentCartPlugin::class);
    }
    
    public function getCartUrl(string $cartId): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }
        
        return route('filament.admin.resources.carts.view', $cartId);
    }
    
    public function getOrderUrl(string $orderId): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }
        
        return route('filament.admin.resources.orders.view', $orderId);
    }
}
```

### Usage in Resources

```php
// In AffiliateConversionResource infolist
Infolists\Components\TextEntry::make('order_reference')
    ->label('Order')
    ->url(fn ($record) => app(CartBridge::class)->getOrderUrl($record->order_id))
    ->openUrlInNewTab()
    ->visible(fn () => app(CartBridge::class)->isAvailable()),
```

---

## VoucherBridge

Enables deep linking from affiliate resources to voucher-related data.

### Purpose

When affiliates have associated voucher codes, allow admins to navigate directly to the voucher in FilamentVouchers.

### Implementation

```php
namespace AIArmada\FilamentAffiliates\Support\Integrations;

final class VoucherBridge
{
    public function isAvailable(): bool
    {
        return class_exists(\AIArmada\FilamentVouchers\FilamentVouchersPlugin::class);
    }
    
    public function getVoucherUrl(string $voucherId): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }
        
        return route('filament.admin.resources.vouchers.view', $voucherId);
    }
    
    public function getVoucherRedemptionUrl(string $redemptionId): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }
        
        return route('filament.admin.resources.voucher-redemptions.view', $redemptionId);
    }
}
```

### Usage in Resources

```php
// In AffiliateResource form
Forms\Components\Select::make('voucher_id')
    ->relationship('voucher', 'code')
    ->searchable()
    ->preload()
    ->visible(fn () => app(VoucherBridge::class)->isAvailable())
    ->suffixAction(
        Forms\Components\Actions\Action::make('viewVoucher')
            ->icon('heroicon-o-external-link')
            ->url(fn ($state) => app(VoucherBridge::class)->getVoucherUrl($state))
            ->openUrlInNewTab()
    ),
```

---

## OrdersBridge (Future)

Potential integration with FilamentOrders for order-level affiliate data.

### Use Cases

| Feature | Description |
|---------|-------------|
| Order Attribution | Show affiliate on order view |
| Commission Panel | Display commission on order |
| Affiliate Link | Navigate to affiliate from order |

---

## CustomersBridge (Future)

Potential integration with FilamentCustomers for customer-affiliate relationships.

### Use Cases

| Feature | Description |
|---------|-------------|
| Customer Affiliate | Show if customer is an affiliate |
| Referral History | Show who referred the customer |
| Affiliate Signup | Quick affiliate registration |

---

## Service Provider Integration

Bridges are registered conditionally in the service provider:

```php
class FilamentAffiliatesServiceProvider extends PackageServiceProvider
{
    public function packageRegistered(): void
    {
        $this->app->singleton(CartBridge::class);
        $this->app->singleton(VoucherBridge::class);
    }
    
    public function packageBooted(): void
    {
        // Auto-detect and configure integrations
        if (class_exists(\AIArmada\FilamentCart\FilamentCartPlugin::class)) {
            $this->configureCartIntegration();
        }
        
        if (class_exists(\AIArmada\FilamentVouchers\FilamentVouchersPlugin::class)) {
            $this->configureVoucherIntegration();
        }
    }
}
```

---

## Configuration

Integrations can be enabled/disabled via config:

```php
// config/filament-affiliates.php
return [
    'integrations' => [
        'cart' => [
            'enabled' => true,
            'deep_links' => true,
        ],
        'vouchers' => [
            'enabled' => true,
            'deep_links' => true,
        ],
        'orders' => [
            'enabled' => true,
            'show_attribution' => true,
        ],
    ],
];
```

---

## Cross-Resource Navigation

### From AffiliateResource

| Target | Condition | Link |
|--------|-----------|------|
| Cart | Cart integration enabled | View related carts |
| Voucher | Voucher linked | View affiliate voucher |
| Order | Order reference exists | View related order |

### To AffiliateResource

| Source | Trigger | Action |
|--------|---------|--------|
| Order | Has affiliate attribution | View affiliate link |
| Voucher | Affiliate voucher type | View affiliate owner |
| Customer | Is affiliate | View affiliate profile |

---

## Navigation

**Previous:** [04-pages.md](04-pages.md)  
**Next:** [PROGRESS.md](PROGRESS.md)
