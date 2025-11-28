# Billing Portal

Customer-facing billing portal for subscription and payment management.

## Requirements

- `aiarmada/cashier-chip` package installed
- User model with `Billable` trait

## Setup

### 1. Register Panel Provider

```php
// config/app.php
'providers' => [
    AIArmada\FilamentChip\BillingPanelProvider::class,
],
```

### 2. Configure User Model

```php
// config/filament-chip.php
'billable' => [
    'model' => App\Models\User::class,
    'billing_portal' => [
        'path' => 'billing',
    ],
],
```

### 3. Add Billable Trait

```php
<?php

namespace App\Models;

use AIArmada\CashierChip\Billable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Billable;
}
```

## Portal Pages

| Page | Route | Description |
|------|-------|-------------|
| Dashboard | `/billing` | Overview with active plans |
| Subscriptions | `/billing/subscriptions` | Manage subscriptions |
| Payment Methods | `/billing/payment-methods` | Saved payment methods |
| Invoices | `/billing/invoices` | Transaction history |

## Access

The portal is login-only. Users must authenticate first—there is no registration form. This ensures only existing users can access billing.

## Extending Pages

Override base pages:

```php
<?php

namespace App\Filament\Pages\Billing;

use AIArmada\FilamentChip\Pages\Billing\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected function getHeaderWidgets(): array
    {
        return [
            // Custom widgets
        ];
    }
}
```
