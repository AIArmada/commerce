---
title: Basic Usage
---

# Basic Usage

This guide covers common workflows for tracking affiliates, recording conversions, and managing commissions.

## Attaching Affiliates to Carts

### Using the Cart Facade (with aiarmada/cart)

```php
use AIArmada\Cart\Facades\Cart;

// Attach affiliate by code
Cart::attachAffiliate('PARTNER42', [
    'utm_source' => 'newsletter',
    'landing_url' => url()->current(),
    'subject_type' => 'product',
    'subject_key' => 'SKU-1001',
    'subject_instance' => 'web',
    'subject_title_snapshot' => 'Pro Plan',
]);

// Check if cart has affiliate
if (Cart::hasAffiliate()) {
    $affiliate = Cart::getAffiliate();
}
```

### Using Actions Directly

```php
use AIArmada\Affiliates\Actions\Affiliates\AttachAffiliateToCart;
use AIArmada\Affiliates\Contracts\AffiliateLookup;

$affiliateLookup = app(AffiliateLookup::class);

// Find affiliate by code
$affiliate = $affiliateLookup->findByCode('PARTNER42');

// Attach to cart with context
$attribution = AttachAffiliateToCart::run($affiliate, $cart, [
    'source' => 'instagram',
    'campaign' => 'summer-sale',
]);
```

## Cookie-Based Tracking

The `TrackAffiliateCookie` middleware automatically captures affiliate visits from URL parameters:

```
https://yoursite.com/products?aff=PARTNER42&utm_source=instagram
```

Recognized parameters (configurable):
- `aff`
- `affiliate`
- `ref`
- `referral`

The middleware:
1. Captures the affiliate code from the URL
2. Creates an `AffiliateAttribution` record with UTM data
3. Sets a tracking cookie (default: 30 days)
4. Links the attribution to the cart when shopping begins

### Consent Management

For GDPR compliance, enable consent requirement:

```php
// config/affiliates.php
'cookies' => [
    'require_consent' => true,
    'consent_cookie' => 'affiliate_consent',
],
```

Set the consent cookie when user accepts:

```php
Cookie::queue('affiliate_consent', '1', 60 * 24 * 365);
```

## Public Referral Entry Routes

When `affiliates.public_pages.enabled` and `affiliates.public_pages.route.enabled` are both `true`, the package registers a public referral entry route.

Default behavior:

- route path: `r/{affiliateCode}`
- route name: `affiliate.referral.entry`
- controller: `PublicAffiliateReferralController`
- action: `CapturePublicAffiliateReferral`

Examples:

```php
route('affiliate.referral.entry', ['affiliateCode' => 'PARTNER42']);

route('affiliate.referral.entry', [
    'affiliateCode' => 'PARTNER42',
    'to' => 'checkout',
]);
```

The entry action resolves the affiliate, records the visit context, persists the affiliate cookie/session metadata, and then redirects to one of the configured public destinations. By default the package recognizes `home` and `checkout`, but you can add your own destination keys under `affiliates.public_pages.route.destinations`.

## Public Page Referral Context

`HydratePublicAffiliateReferralContext` is the request middleware that turns the stored referral into a small public-page payload for banners, hero copy, and checkout entry points.

When `affiliates.public_pages.auto_register_middleware` is enabled, the middleware is pushed into the `web` group automatically. You can also attach it manually with the alias:

```php
Route::middleware('affiliates.public_context')->group(function (): void {
    Route::view('/landing', 'landing.show');
});
```

The package also shares the resolved context with all views using the configured `affiliates.public_pages.view_data_key` (default: `affiliateReferral`).

Typical payload fields include:

- `code`
- `name`
- `default_voucher_code`
- `entry_url`
- `checkout_url`

The package ships a ready-made banner view at `affiliates::components.public-referral-banner` that expects this payload shape, so applications can reuse or adapt it for public landing pages and checkout surfaces.

## Recording Conversions

### From Cart

```php
use AIArmada\Cart\Facades\Cart;

// Record conversion when order is placed
Cart::recordAffiliateConversion([
    'external_reference' => $order->reference,
    'subtotal' => $order->subtotal_minor,
    'total' => $order->total_minor,
    'conversion_type' => 'purchase',
]);
```

### Orders Integration (Auto Attribution)

When the Orders package is installed, it can emit a commission attribution event on payment.
The Affiliates package resolves the normalized attribution for the original cart and records
conversions automatically.

```php
use AIArmada\Orders\Models\Order;

$order = Order::create([
    // ...
    'metadata' => [
        'cart_id' => $cart->getId(),
    ],
]);
```

### Direct Recording

```php
use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateConversion;

$conversion = RecordAffiliateConversion::run(
    cart: $cart,
    payload: [
        'external_reference' => 'ORD-12345',
        'value_minor' => 15000,
        'subtotal_minor' => 14000,
        'conversion_type' => 'purchase',
        'subject_type' => 'product',
        'subject_key' => 'SKU-1001',
        'subject_instance' => 'web',
        'subject_title_snapshot' => 'Pro Plan',
        'metadata' => [
            'customer_id' => $customer->id,
            'items' => $order->items->count(),
        ],
    ]
);
```

## Creating Subject-Aware Tracking Links

```php
$link = $service->createTrackingLink($affiliate, 'https://example.com/products/sku-1001', [
    'params' => ['utm_source' => 'affiliate-campaign'],
    'ttl_seconds' => 3600,
    'subject_type' => 'product',
    'subject_key' => 'SKU-1001',
    'subject_instance' => 'web',
    'subject_title_snapshot' => 'Pro Plan',
    'subject_metadata' => [
        'category' => 'subscriptions',
    ],
]);
```

### Conversion Statuses

```php
use AIArmada\Affiliates\Enums\ConversionStatus;

ConversionStatus::Pending;    // Awaiting review
ConversionStatus::Qualified;  // Qualified and waiting for maturity processing
ConversionStatus::Approved;   // Approved and released toward payout eligibility
ConversionStatus::Rejected;   // Rejected (fraud, refund, etc.)
ConversionStatus::Paid;       // Commission paid out
```

Use `total` and `subtotal` as the conversion amount inputs. The persisted conversion uses
`value_minor` and `external_reference`; cart identity is resolved from the active attribution.

When the maturity workflow is enabled, conversions typically move from `Pending` into `Qualified`, remain in holding, and then become `Approved` when `affiliates:process-maturity` runs after the configured maturity window.

## Commission Calculation

The `CommissionCalculator` service handles all commission logic:

```php
use AIArmada\Affiliates\Services\CommissionCalculator;

$calculator = app(CommissionCalculator::class);

// Calculate commission for an order
$commission = $calculator->calculate(
    affiliate: $affiliate,
    orderTotal: 15000,
    orderSubtotal: 14000,
);

// Returns commission in minor units (for example, 1500 = 15.00 in the affiliate currency)
```

### Commission Types

```php
use AIArmada\Affiliates\Enums\CommissionType;

// Percentage (in basis points: 1000 = 10%)
$affiliate->commission_type = CommissionType::Percentage;
$affiliate->commission_rate = 1000; // 10%

// Fixed amount (in minor units)
$affiliate->commission_type = CommissionType::Fixed;
$affiliate->commission_rate = 500;
```

## Working with Affiliates

### Creating Affiliates

```php
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\States\Active;

$affiliate = Affiliate::create([
    'code' => 'PARTNER42',
    'name' => 'John Partner',
    'status' => Active::class,
    'commission_type' => CommissionType::Percentage,
    'commission_rate' => 1000, // 10%
    'currency' => 'MYR',
    'contact_email' => 'john@partner.com',
]);
```

### Affiliate Statuses

```php
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Draft;
use AIArmada\Affiliates\States\Paused;
use AIArmada\Affiliates\States\Pending;

AffiliateStatus::normalize(Draft::class);    // Not yet active
AffiliateStatus::normalize(Pending::class);  // Awaiting approval
AffiliateStatus::normalize(Active::class);   // Active and earning
AffiliateStatus::normalize(Paused::class);   // Temporarily paused
AffiliateStatus::normalize(Disabled::class); // Disabled
```

Affiliate lifecycle is implemented with Spatie model states, not a backed enum. In write paths you can assign the state class directly, for example `Pending::class` or `Active::class`.

## Affiliate Network / Downlines

### Viewing Downlines on the Dashboard

When an affiliate has downlines (affiliates they referred), the affiliate portal dashboard displays a **Your Network** section showing a table of direct downlines with their name, code, rank, conversion count, and status.

Downlines are loaded via the `children()` relationship on the `Affiliate` model (`parent_affiliate_id`).

### Registration with Referral Code

New affiliates can optionally enter a referral code during self-registration. If the code matches an existing affiliate:

- The new affiliate's `parent_affiliate_id` is set to the referrer
- The `NetworkService::addToNetwork()` is called to build the closure table when network features are enabled

```php
// The portal registration form includes a "Referral Code (optional)" field.
// When a valid code is submitted, the new affiliate is automatically linked
// as a downline of the referring affiliate.
```

### Working with the Network Programmatically

```php
use AIArmada\Affiliates\Services\NetworkService;

$network = app(NetworkService::class);

// Get direct downlines
$downlines = $network->getDirectRecruits($affiliate);

// Get entire downline tree
$allDownlines = $network->getDownline($affiliate);

// Get upline
$upline = $network->getUpline($affiliate);

// Build a tree structure for visualization
$tree = $network->buildTree($affiliate, $maxDepth = 3);
```

### Querying Affiliates

```php
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;

// Get active affiliates
$active = Affiliate::query()
    ->where('status', AffiliateStatus::normalize(Active::class))
    ->get();

// Find by default voucher code
$affiliate = $service->findByDefaultVoucherCode('SUMMER20');

// Get affiliate with relationships
$affiliate = Affiliate::with(['conversions', 'payouts', 'balance'])
    ->find($id);
```

## Events

Listen to affiliate events for automation:

```php
use AIArmada\Affiliates\Events\AffiliateAttributed;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;

// In EventServiceProvider
protected $listen = [
    AffiliateAttributed::class => [
        SendWelcomeEmail::class,
        NotifySlack::class,
    ],
    AffiliateConversionRecorded::class => [
        UpdateAffiliateStats::class,
        SendConversionNotification::class,
    ],
];
```

## Facades

Use the `Affiliates` facade for quick access:

```php
use AIArmada\Affiliates\Facades\Affiliates;

$affiliate = Affiliates::findByCode('PARTNER42');
$attribution = Affiliates::attachToCartByCode('PARTNER42', $cart);
```
