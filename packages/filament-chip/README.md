# AIArmada Filament CHIP

> A comprehensive Filament v5 admin plugin for exploring and managing CHIP payment data ingested by the [aiarmada/chip](https://github.com/aiarmada/chip) package.

[![Packagist](https://img.shields.io/packagist/v/aiarmada/filament-chip.svg?style=flat-square)](https://packagist.org/packages/aiarmada/filament-chip)
[![Tests](https://img.shields.io/github/actions/workflow/status/aiarmada/filament-chip/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/aiarmada/filament-chip/actions)

---

## Features

- 💳 **Purchase Management** – View, filter, refund, and cancel CHIP purchases
- 🏦 **CHIP Send Integration** – Manage payouts, bank accounts, and send instructions
- 👤 **Client Explorer** – Browse customer profiles and payment history
- 📊 **Company Statements** – Financial reports and settlement tracking
- 🔔 **Webhook Monitoring** – Real-time webhook event logs and processing status
- 📈 **Analytics Dashboard** – Payment metrics, trends, and statistics
- 🔐 **Read-Only by Default** – Safe data exploration with optional mutation actions
- ⚡ **Real-Time Sync** – Automatic model updates from CHIP API via events
- 🏠 **Customer Billing Portal** – Self-hosted billing portal for customers to manage subscriptions, payment methods, and invoices

---

## Requirements

- PHP ^8.4
- Laravel ^12.0
- Filament ^5.0
- aiarmada/chip ^0.1
- aiarmada/cashier-chip ^0.1 (for Billing Portal)

---

## Installation

```bash
composer require aiarmada/filament-chip
```

The service provider auto-registers with Laravel's package discovery.

### Register the Admin Plugin

Add the plugin to your Filament panel in `app/Providers/Filament/AdminPanelProvider.php`:

```php
use AIArmada\FilamentChip\FilamentChipPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...existing configuration
        ->plugin(FilamentChipPlugin::make());
}
```

### Register the Billing Portal (Optional)

To enable the customer-facing billing portal, register the `BillingPanelProvider` in your `AppServiceProvider`:

```php
use AIArmada\FilamentChip\BillingPanelProvider;
use Filament\Facades\Filament;

public function boot(): void
{
    Filament::registerPanel(
        BillingPanelProvider::make()
            ->path('billing')           // URL path (default: /billing)
            ->authGuard('web')          // Authentication guard
            ->billableModel(\App\Models\User::class)
    );
}
```

### Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag="filament-chip-config"
```

---

## Resources

The plugin provides the following Filament resources:

### 1. Purchase Resource

**Location:** CHIP → Purchases

View and manage CHIP payment purchases with comprehensive details:

- **Table Columns:**
  - ID (with copy action)
  - Status badge (paid, pending, failed, refunded)
  - Amount with currency
  - Client email and name
  - Payment method
  - Created/updated dates

- **Filters:**
  - Status (paid, pending, failed, refunded, cancelled)
  - Date range (created, updated)
  - Amount range
  - Payment method
  - Brand ID

- **Actions:**
  - View full purchase details (infolist view)
  - Refund (for paid purchases)
  - Cancel (for pending purchases)
  - Resend invoice
  - Mark as paid (manual override)

**Infolist Sections:**
- Purchase information (ID, status, amounts)
- Client details (email, name, phone)
- Payment details (method, reference, transaction ID)
- Products (line items with prices)
- Timeline (created, updated, paid at)
- Metadata (brand, due date, notes)

### 2. Payment Resource

**Location:** CHIP → Payments

Track individual payment transactions linked to purchases:

- Transaction ID and status
- Payment method and processor
- Amount and currency
- Authorization details
- Timestamps

### 3. Client Resource

**Location:** CHIP → Clients

Browse customer profiles synchronized from CHIP:

- Client ID with copy action
- Email and full name
- Phone number
- Related purchases count
- Recurring tokens
- Creation date

**Relations:**
- View all purchases for a client
- Manage recurring payment tokens

### 4. Webhook Resource

**Location:** CHIP → Webhooks

Monitor webhook configurations and event logs:

- Webhook ID and URL
- Event types subscribed
- Status (active/inactive)
- Last triggered timestamp
- Signature verification status

**Event Types:**
- `purchase.paid`
- `purchase.refunded`
- `purchase.cancelled`
- `purchase.failed`
- Custom events

### 5. Company Statement Resource

**Location:** CHIP → Company Statements

Financial reports and settlement tracking:

- Statement ID and period
- Total amount and currency
- Settlement status
- Generated date
- Download statement (PDF/CSV)

**Actions:**
- View detailed breakdown
- Cancel statement (if pending)

### 6. Send Instruction Resource (CHIP Send)

**Location:** CHIP Send → Instructions

Manage payout instructions for disbursements:

- Instruction ID and reference
- Recipient bank account
- Amount and currency
- Status (pending, processing, completed, failed)
- Description and notes

**Actions:**
- View instruction details
- Cancel pending instructions
- Delete failed instructions
- Resend webhook notification

### 7. Bank Account Resource (CHIP Send)

**Location:** CHIP Send → Bank Accounts

Manage recipient bank accounts for payouts:

- Bank name and account number
- Account holder name
- Account type (individual, business)
- Verification status
- Created date

**Actions:**
- View account details
- Edit account information
- Delete account
- Resend verification webhook

---

## Dashboard Widgets

### CHIP Stats Overview

Displays key metrics at a glance:

- Total purchases count
- Total revenue (paid purchases)
- Pending purchases count
- Failed purchases count
- Refund total
- Active clients count
- Webhook events today

**Widget Configuration:**
```php
FilamentChipPlugin::make()
    ->widgets([
        ChipStatsWidget::class,
    ]);
```

---

## Customer Billing Portal

The package includes a self-hosted customer billing portal that provides a Stripe-like billing experience for CHIP payments. Unlike Stripe's native `billingPortalUrl()`, CHIP doesn't offer a hosted portal, so this package implements one as a separate Filament panel.

### Features

- **Dashboard** – Overview of subscriptions, payment methods, and recent invoices
- **Subscriptions** – View active subscriptions, cancel or resume
- **Payment Methods** – Add new cards via CHIP's zero-amount preauthorization, set default, delete
- **Invoices** – View billing history and download invoice PDFs

### Configuration

Configure the billing portal in `config/filament-chip.php`:

```php
'billing' => [
    // Enable or disable the billing portal
    'enabled' => env('CHIP_BILLING_PORTAL_ENABLED', true),

    // Panel ID for the billing portal
    'panel_id' => 'billing',

    // Path prefix for the billing portal (e.g., /billing)
    'path' => 'billing',

    // Authentication guard for the billing portal
    'auth_guard' => 'web',

    // The billable model (user or team)
    'billable_model' => \App\Models\User::class,

    // Features to enable in the billing portal
    'features' => [
        'subscriptions' => true,
        'payment_methods' => true,
        'invoices' => true,
    ],

    // Redirect URLs after actions
    'redirects' => [
        'after_payment_method_added' => null,
        'after_subscription_cancelled' => null,
    ],

    // Invoice configuration
    'invoice' => [
        'vendor_name' => null, // Falls back to config('app.name')
        'product_name' => 'Subscription',
    ],
],
```

### Getting the Portal URL

In your application, redirect customers to the billing portal using the `customerPortalUrl()` method from the gateway:

```php
use AIArmada\Cashier\Facades\Gateway;

// Get the billing portal URL
$url = Gateway::driver('chip')->customerPortalUrl(
    returnUrl: route('home'),
    options: ['panel_id' => 'billing']
);

return redirect($url);
```

Or use the billable's method if available:

```php
// From a user/team that uses the Billable trait
$url = $user->customerPortalUrl();
```

### Adding Payment Methods

The portal uses CHIP's zero-amount preauthorization to securely add payment methods without charging the customer:

```php
// Get the URL to add a new payment method
$url = $user->setupPaymentMethodUrl([
    'success_url' => route('billing.payment-methods'),
    'cancel_url' => route('billing.payment-methods'),
]);
```

This creates a CHIP purchase with:
- `total_override = 0` (zero amount)
- `skip_capture = true` (preauthorization only)
- `force_recurring = true` (save card for future use)

### Portal Pages

| Page | URL | Description |
|------|-----|-------------|
| Dashboard | `/billing` | Overview of account |
| Subscriptions | `/billing/subscriptions` | Manage subscriptions |
| Payment Methods | `/billing/payment-methods` | Manage saved cards |
| Invoices | `/billing/invoices` | Billing history |

### Customizing the Portal

You can extend the billing pages to add custom functionality:

```php
namespace App\Filament\Billing\Pages;

use AIArmada\FilamentChip\Pages\Billing\BillingDashboard as BaseDashboard;

class BillingDashboard extends BaseDashboard
{
    protected string $view = 'filament.billing.dashboard';
    
    public function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'customData' => $this->getCustomData(),
        ]);
    }
}

---

## Configuration

Customize the plugin via `config/filament-chip.php`:

```php
return [
    // Navigation settings
    'navigation' => [
        'group' => 'CHIP Payments',
        'sort' => 10,
        'icon' => 'heroicon-o-credit-card',
    ],
    
    // Resource configuration
    'resources' => [
        'purchase' => [
            'enabled' => true,
            'label' => 'Purchases',
            'plural_label' => 'Purchases',
            'navigation_sort' => 1,
        ],
        'client' => [
            'enabled' => true,
            'label' => 'Client',
            'plural_label' => 'Clients',
            'navigation_sort' => 2,
        ],
        'webhook' => [
            'enabled' => true,
            'label' => 'Webhook',
            'plural_label' => 'Webhooks',
            'navigation_sort' => 3,
        ],
        'send_instruction' => [
            'enabled' => true,
            'label' => 'Send Instruction',
            'plural_label' => 'Send Instructions',
            'navigation_sort' => 4,
        ],
    ],
    
    // Table settings
    'table' => [
        'default_sort' => 'created_at',
        'default_sort_direction' => 'desc',
        'records_per_page' => 25,
    ],
    
    // Enable/disable specific actions
    'actions' => [
        'refund' => true,
        'cancel' => true,
        'resend_invoice' => true,
        'mark_as_paid' => true, // Use with caution
    ],
];
```

---

## Authorization

The plugin respects Filament's authorization system. Define policies for fine-grained access control:

```php
// app/Policies/ChipPurchasePolicy.php
class ChipPurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_chip_purchases');
    }
    
    public function view(User $user, ChipPurchase $purchase): bool
    {
        return $user->can('view_chip_purchases');
    }
    
    public function refund(User $user, ChipPurchase $purchase): bool
    {
        return $user->can('refund_chip_purchases') 
            && $purchase->status === 'paid';
    }
    
    public function cancel(User $user, ChipPurchase $purchase): bool
    {
        return $user->can('cancel_chip_purchases')
            && $purchase->status === 'pending';
    }
}
```

Register the policy in your `AuthServiceProvider`:

```php
protected $policies = [
    ChipPurchase::class => ChipPurchasePolicy::class,
    ChipClient::class => ChipClientPolicy::class,
    // ... other CHIP models
];
```

---

## Testing

The plugin includes comprehensive tests using Pest v4:

```bash
# Run all tests
vendor/bin/pest

# Run specific test suite
vendor/bin/pest --filter=PurchaseResource

# Run with coverage
vendor/bin/pest --coverage
```

**Note:** Test coverage for this package is currently **partial**. The following areas need additional tests:
- Send instruction resource actions
- Bank account resource operations
- Webhook event processing
- Dashboard widget calculations
- Policy authorization scenarios

---

## Model Synchronization

The plugin automatically syncs CHIP data from the API to local database models using Laravel events:

- `PurchaseCreated` → Creates `ChipPurchase` record
- `PurchaseUpdated` → Updates `ChipPurchase` status
- `WebhookReceived` → Logs webhook event
- `SendInstructionCreated` → Creates send instruction record

This enables fast querying, filtering, and reporting without repeated API calls.

### Manual Sync Command

Force synchronization of all CHIP data:

```bash
php artisan chip:sync
```

Options:
- `--purchases` - Sync only purchases
- `--clients` - Sync only clients
- `--webhooks` - Sync only webhooks
- `--from=2025-01-01` - Sync from specific date

---

## Extending Resources

You can extend the resources to add custom functionality:

```php
// app/Filament/Resources/CustomPurchaseResource.php
namespace App\Filament\Resources;

use AIArmada\FilamentChip\Resources\PurchaseResource as BasePurchaseResource;
use Filament\Tables;

class CustomPurchaseResource extends BasePurchaseResource
{
    // Add custom table columns
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                ...parent::getColumns(),
                Tables\Columns\TextColumn::make('custom_field')
                    ->label('Custom Data'),
            ]);
    }
    
    // Add custom actions
    public static function getActions(): array
    {
        return [
            ...parent::getActions(),
            Tables\Actions\Action::make('custom_action')
                ->label('Custom Action')
                ->action(fn ($record) => /* your logic */),
        ];
    }
}
```

Then register your custom resource instead of the default:

```php
FilamentChipPlugin::make()
    ->resources([
        CustomPurchaseResource::class,
    ]);
```

---

## Troubleshooting

### Purchases not showing

1. Verify CHIP API credentials in `.env`
2. Check that `aiarmada/chip` is properly configured
3. Run manual sync: `php artisan chip:sync --purchases`
4. Check logs for API errors

### Webhook events missing

1. Ensure webhook URL is registered in CHIP dashboard
2. Verify `CHIP_WEBHOOK_PUBLIC_KEY` is set in `.env`
3. Check webhook logs: `php artisan chip:webhooks --recent`
4. Test webhook signature verification

### Actions failing

1. Check user permissions and policies
2. Verify purchase status allows the action (e.g., can't refund pending)
3. Check CHIP API connectivity: `php artisan chip:health`
4. Review Laravel logs for exceptions

---

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](../../CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone the monorepo
git clone https://github.com/aiarmada/commerce.git
cd commerce

# Install dependencies
composer install

# Run package tests
cd packages/filament-chip
composer test

# Format code
vendor/bin/pint --dirty
```

---

## Security

If you discover any security issues, please email security@aiarmada.com instead of using the issue tracker.

---

## Credits

- [AIArmada Team](https://aiarmada.com)
- [All Contributors](https://github.com/aiarmada/commerce/contributors)

---

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
