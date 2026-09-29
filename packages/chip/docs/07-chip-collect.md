---
title: CHIP Collect
---

# CHIP Collect

Accept payments via FPX, cards, e-wallets, and more.

## Facade

```php
use AIArmada\Chip\Facades\Chip;
```

## Create Purchase

### Array Method

```php
$purchase = Chip::createPurchase([
    'brand_id' => config('chip.collect.brand_id'),
    'client' => [
        'email' => 'customer@example.com',
        'full_name' => 'John Doe',
        'phone' => '+60123456789',
    ],
    'purchase' => [
        'currency' => 'MYR',
        'products' => [
            ['name' => 'Product A', 'price' => 5000, 'quantity' => '2'],
            ['name' => 'Product B', 'price' => 3000, 'quantity' => '1'],
        ],
    ],
    'success_redirect' => route('checkout.success'),
    'failure_redirect' => route('checkout.failed'),
]);

return redirect($purchase->checkout_url);
```

### Fluent Builder

```php
$purchase = Chip::purchase()
    ->currency('MYR')
    ->customer('customer@example.com', 'John Doe', '+60123456789')
    ->addProductCents('Product A', 5000, 2)
    ->addProductCents('Product B', 3000, 1)
    ->successUrl(route('checkout.success'))
    ->failureUrl(route('checkout.failed'))
    ->sendReceipt(true)
    ->create();
```

### With Money Objects

```php
use Akaunting\Money\Money;

Chip::purchase()
    ->customer('customer@example.com')
    ->currency('MYR')
    ->addProductMoney('Product', Money::MYR(9900), 1)
    ->successUrl(route('success'))
    ->create();
```

### Extended purchase fields

```php
use AIArmada\Chip\Enums\RequestClientDetail;

Chip::purchase()
    ->customer('customer@example.com')
    ->currency('MYR')
    ->addProductCents('Product', 9900, 1, 0, 6.0, null, 9500)
    ->issued('2026-01-01')
    ->paymentMethodWhitelist(['fpx', 'visa'])
    ->tags(['promo'])
    ->language('en')
    ->debt(-500)
    ->timezone('Asia/Kuala_Lumpur')
    ->emailMessage('Thanks for your order!')
    ->requestClientDetails([RequestClientDetail::PHONE])
    ->legalName('Legal Co')
    ->successUrl(route('success'))
    ->create();
```

`issued` is a display-only date string; the whitelist rejects unknown methods; `debt` may be negative (subtracted from the total). Length and range bounds throw before any request — see [Conventions](10-api-reference.md#conventions).

### FPX Direct Post

```php
use AIArmada\Chip\Enums\FpxBank;
use AIArmada\Chip\Enums\FpxType;

$purchase = Chip::purchase()
    ->customer('customer@example.com')
    ->currency('MYR')
    ->addProductCents('Product', 9900)
    ->successUrl(route('success'))
    ->create();

// Always build on checkout_url (/p/{purchase_id}/). direct_post_url is a
// different integration (card form POST) — never a base for query args.
$checkoutRedirect = $purchase->checkout_url
    .'?preferred='.FpxType::B2B1->value
    .'&fpx_bank_code='.FpxBank::PUBLIC_BANK_PB_ENTERPRISE->value;

return redirect($checkoutRedirect);
```

Use `FpxType::B2C` with a B2C bank like `FpxBank::MAYBANK2U` for personal-banking flows, or `FpxType::B2B1` with corporate bank codes like `FpxBank::PUBLIC_BANK_PB_ENTERPRISE`, `FpxBank::AFFINMAX`, or `FpxBank::UOB_REGIONAL` for business-banking flows.

To pre-select FPX without skipping the gateway page, use `?active=` instead of `?preferred=` — the customer still sees the default payment page and can switch methods:

```php
$checkoutRedirect = $purchase->checkout_url
    .'?active='.FpxType::B2C->value;
```

To collect raw card details instead of redirecting, POST a card form to `direct_post_url` — see [Card payments](#card-payments).

### E-Wallet Direct Post

```php
use AIArmada\Chip\Enums\EWallet;

$checkoutRedirect = $purchase->checkout_url
    .'?'.http_build_query(EWallet::GRABPAY->urlParams());

return redirect($checkoutRedirect);
```

Like FPX, `?active=` pre-selects a wallet on the gateway page without skipping it.

Most wallets append both `preferred` and `razer_bank_code`:

| Wallet | `preferred` | `razer_bank_code` |
|---|---|---|
| GrabPay | `razer_grabpay` | `GrabPay` |
| Touch 'n Go eWallet | `razer_tng` | `TNG-EWALLET` |
| Maybank QR | `razer_maybankqr` | `MB2U_QRPay-Push` |
| Atome | `razer_atome` | `Atome` |

ShopeePay is the exception: `?preferred=shopee_pay` with no `razer_bank_code`
(`EWallet::SHOPEEPAY->urlParams()` returns the `preferred` key only).

Breaking change: ShopeePay previously used `?preferred=razer_shopeepay&razer_bank_code=ShopeePay`.
`EWallet::fromPreferred('razer_shopeepay')` now returns `null`, and
`EWallet::toArray()['SHOPEEPAY']['code']` is now `null` instead of
`'ShopeePay'`. If you persisted a `preferred` string or build URLs off
`toArray()`, update the stored value and null-check `code` (or switch to
`urlParams()`, which omits the missing key).

### Card payments

To collect card details on your own page, POST a card form to the purchase's `direct_post_url`:

```html
<form method="POST" action="{{ $purchase->direct_post_url }}">
    <input type="text" name="cardholder_name" maxlength="30">
    <input type="text" name="card_number" maxlength="19">
    <input type="text" name="expires" placeholder="MM/YY" maxlength="5">
    <input type="text" name="cvc" maxlength="4">
    <input type="checkbox" name="remember_card" value="on">
    <button type="submit">Pay</button>
</form>
```

Field rules: `cardholder_name` is Latin letters plus space, apostrophe, dot, dash (max 30 chars); `card_number` is digits only, no whitespace (max 19 chars); `expires` is strict `MM/YY` (`/^\d{2}\/\d{2}$/`, max 5 chars); `cvc` is a numeric string of 3 or 4 digits. Validation errors are treated as payment failures (e.g. `validation_cvc_not_provided`).

Prerequisites: Direct Post is activated per merchant account (ask your account manager). `direct_post_url` is null when payment is impossible, when `purchase.request_client_details` is non-empty, or when success/failure redirects are missing — any of the three breaks the flow.

Passing `remember_card=on` tokenizes the card: the purchase ID serves as the card token (`is_recurring_token` becomes `true`). To charge it again, create a new purchase and call `POST /purchases/{new_id}/charge/` with `{"recurring_token": "{initial_purchase_id}"}`; the token persists in the new purchase's `recurring_token` field. Delete it with `POST /purchases/{initial_id}/delete_recurring_token/` (`is_recurring_token` resets to `false`).

### Test cards

Test-drive any checkout with a test purchase and these cards (any cardholder name, CVC `123`):

- `4444 3333 2222 1111` — non-3D Secure
- `5555 5555 5555 4444` — 3D Secure enrolled

The testing docs allow any expiry at or after the current month/year, while the purchase-create spec prose says "greater than now". The live Direct Post card attempt was inconclusive: it returned the JS-gated form and the purchase stayed `viewed`, so current-month expiry acceptance remains unverified. For a failed payment, change the CVC or expiry — but the failure modes differ in S2S checkout: a wrong CVC on the 3D card fails authorization after the customer returns from the test ACS, while a wrong expiry fails data validation immediately.

Expiry-format conflict: the testing prose allows any expiry at or after the current month/year, but the Direct Post `expires` field only accepts strict `MM/YY` (5 chars). Use well-formed `MM/YY` in a future month to satisfy both expiry passages while current-month acceptance remains unverified. A malformed value fails form validation; a well-formed wrong one fails server-side.

### From Checkoutable

```php
$cart = app(\AIArmada\Cart\Cart::class);

$purchase = Chip::purchase()
    ->fromCheckoutable($cart)
    ->fromCustomer($customer)
    ->successUrl(route('success'))
    ->create();
```

When the customer exposes a gateway customer ID, `fromCustomer()` sends
`client_id` only and skips the client-details build: CHIP snapshots the
linked Client record at creation, so the linked record wins. Prefer passing
explicit client details (or no gateway ID) when the local data is fresher —
a stale link ships stale billing data with no signal.

## Purchase Operations

### Retrieve

```php
$purchase = Chip::getPurchase('pur_abc123');

$purchase->id;
$purchase->status;           // 'created', 'paid', 'cancelled'
$purchase->getAmount();      // Money object
$purchase->getAmountInCents();
$purchase->getCheckoutUrl();
$purchase->isPaid();
$purchase->isRefunded();
$purchase->isCancelled();
```

### Cancel

```php
$purchase = Chip::cancelPurchase('pur_abc123');
```

### Refund

```php
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\PurchaseData;

// Full refund
$refund = Chip::refundPurchase('pur_abc123');

// Partial refund (in cents)
$refund = Chip::refundPurchase('pur_abc123', 5000);

if ($refund instanceof PaymentData) {
    $refund->getAmountInCents();
}

if ($refund instanceof PurchaseData && $refund->status === 'pending_refund') {
    // Wait for the payment.refunded webhook.
}
```

Completed refunds return a `PaymentData` object. If the acquirer is still processing the refund, CHIP returns a `PurchaseData` object with `status = pending_refund` until the later `payment.refunded` callback arrives.

### Capture (Pre-auth)

```php
// Full capture
$purchase = Chip::capturePurchase('pur_abc123');

// Partial capture
$purchase = Chip::capturePurchase('pur_abc123', 5000);
```

### Release Hold

```php
$purchase = Chip::releasePurchase('pur_abc123');
```

### Mark as Paid

```php
$purchase = Chip::markPurchaseAsPaid('pur_abc123');
```

### Resend Invoice

```php
$purchase = Chip::resendInvoice('pur_abc123');
```

### Payment amounts

A payment carries three amounts: `amount = net_amount + fee_amount + pending_amount`. The account is credited or debited with the NET amount — display and reconcile against `net_amount`, not `amount`. The same fee is also exposed per attempt at `transaction_data.attempts[].fee_amount` (alongside `markup_amount` and `processing_fee`).

## Clients

```php
// Create
$client = Chip::createClient([
    'email' => 'customer@example.com',
    'full_name' => 'John Doe',
]);

// Retrieve
$client = Chip::getClient('cli_abc123');

// List
$clients = Chip::listClients(['limit' => 50]);

// Update
$client = Chip::updateClient('cli_abc123', ['full_name' => 'Jane Doe']);

// Delete
Chip::deleteClient('cli_abc123');
```

## Payment Methods

```php
$methods = Chip::getPaymentMethods([
    'currency' => 'MYR',
]);
```

## Account

```php
$balance = Chip::getAccountBalance();
$turnover = Chip::getAccountTurnover([
    'from' => strtotime('2025-01-01'),
    'to' => strtotime('2025-01-31'),
    'currency' => 'MYR',
]);
```

## Company Statements

```php
$statements = Chip::listCompanyStatements();
$statement = Chip::getCompanyStatement('stmt_abc123');
$statement = Chip::cancelCompanyStatement('stmt_abc123');
```

## Public Key

```php
$publicKey = Chip::getPublicKey();
```

## Next Steps

- [CHIP Send](08-chip-send.md) – Disbursements
- [Webhooks](09-webhooks.md) – Event handling

## Official references

- [CHIP Collect documentation](https://docs.chip-in.asia/)
- [Collect API keys](https://portal.chip-in.asia/collect/developers/api-keys)
- [Collect Brand IDs](https://portal.chip-in.asia/collect/developers/brands)
