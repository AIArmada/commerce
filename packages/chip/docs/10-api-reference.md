---
title: API Reference
---

# API Reference

Complete method reference for CHIP package.

## Base URLs

| Service | Environment | URL |
|---------|-------------|-----|
| Collect | All | `https://gate.chip-in.asia/api/v1/` |
| Send | Sandbox | `https://staging-api.chip-in.asia/api/` |
| Send | Production | `https://api.chip-in.asia/api/` |

## Authentication

### Collect

```http
Authorization: Bearer {CHIP_COLLECT_API_KEY}
```

### Send

```http
Authorization: Bearer {CHIP_SEND_API_KEY}
epoch: {unix_timestamp}
checksum: {hmac_sha512(epoch + CHIP_SEND_API_KEY, CHIP_SEND_API_SECRET)}
```

## ChipGateway

```php
use AIArmada\Chip\Gateways\ChipGateway;

$gateway = app(ChipGateway::class);

$gateway->getName(): string                    // 'chip'
$gateway->getDisplayName(): string             // 'CHIP'
$gateway->isTestMode(): bool

$gateway->createPayment(
    CheckoutableInterface $checkoutable,
    ?CustomerInterface $customer,
    array $options
): PaymentIntentInterface

$gateway->getPayment(string $paymentId): PaymentIntentInterface
$gateway->cancelPayment(string $paymentId): PaymentIntentInterface
$gateway->refundPayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface
$gateway->capturePayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface
$gateway->getPaymentMethods(array $filters = []): array
$gateway->supports(string $feature): bool
$gateway->getWebhookHandler(): WebhookHandlerInterface
```

## ChipCollectService (Chip Facade)

```php
use AIArmada\Chip\Facades\Chip;

// Purchases
Chip::purchase(): PurchaseBuilder
Chip::createPurchase(array $data, ?string $idempotencyKey = null): PurchaseData
Chip::getPurchase(string $id): PurchaseData
Chip::cancelPurchase(string $id, ?string $idempotencyKey = null): PurchaseData
Chip::refundPurchase(string $id, ?int $amount = null, ?string $idempotencyKey = null): PaymentData|PurchaseData
Chip::chargePurchase(string $id, string $recurringToken, ?string $idempotencyKey = null): PurchaseData
Chip::capturePurchase(string $id, ?int $amount = null, ?string $idempotencyKey = null): PurchaseData
Chip::releasePurchase(string $id, ?string $idempotencyKey = null): PurchaseData
Chip::markPurchaseAsPaid(string $id, ?int $paidOn = null, ?string $idempotencyKey = null): PurchaseData
Chip::resendInvoice(string $id, ?string $idempotencyKey = null): PurchaseData
Chip::deleteRecurringToken(string $id, ?string $idempotencyKey = null): PurchaseData
Chip::getPaymentMethods(array $filters = []): array

// Clients
Chip::createClient(array $data): ClientData
Chip::getClient(string $id): ClientData
Chip::listClients(array $filters = []): array
Chip::updateClient(string $id, array $data): ClientData
Chip::partialUpdateClient(string $id, array $data): ClientData
Chip::deleteClient(string $id): void

// Account
Chip::getAccountBalance(array $filters = []): array
Chip::getAccountTurnover(array $filters = []): array
Chip::listCompanyStatements(array $filters = []): array
Chip::getCompanyStatement(string $id): CompanyStatementData
Chip::cancelCompanyStatement(string $id): CompanyStatementData
Chip::scheduleCompanyStatement(array $statement, array $filters = []): CompanyStatementData

// Webhooks
Chip::createWebhook(array $data): array
Chip::getWebhook(string $id): array
Chip::updateWebhook(string $id, array $data): array
Chip::partialUpdateWebhook(string $id, array $data): array
Chip::deleteWebhook(string $id): void
Chip::listWebhooks(array $filters = []): array

// Public Key
Chip::getPublicKey(): string
Chip::getBrandId(): string
```

Idempotency invariant: a resolved key (explicit argument, payload `idempotency_key`, `reference` fallback, or the checkout fingerprint default) is always sent as an `Idempotency-Key` header. Caller-supplied keys are sent as given (trimmed; empty throws) — never generated, never derived; the fingerprint default (`checkout-` + payload hash) is the deliberate exception for keyless creates. Keys are scoped to purchase operations. The checkout-builder path stays keyless: it has no stable operation identity.

## ChipSendService (ChipSend Facade)

```php
use AIArmada\Chip\Facades\ChipSend;

// Send Instructions
ChipSend::createSendInstruction(
    int $amountInCents,
    int $recipientBankAccountId,
    string $description,
    string $reference,
    string $email,
    bool $sendRecipientReceipt = false
): SendInstructionData

ChipSend::getSendInstruction(int $id): SendInstructionData
ChipSend::listSendInstructions(array $filters = []): array
ChipSend::deleteSendInstruction(int $id): void
ChipSend::resendSendInstructionWebhook(int $id): array

// Bank Accounts
ChipSend::createBankAccount(
    string $bankCode,
    string $accountNumber,
    string $accountHolderName,
    string $reference
): BankAccountData

ChipSend::getBankAccount(int $id): BankAccountData
ChipSend::listBankAccounts(array $filters = []): array
ChipSend::deleteBankAccount(int $id): void
ChipSend::resendBankAccountWebhook(int $id): array

// Send Limits
ChipSend::getSendLimit(int $id): SendLimitData
ChipSend::increaseBudgetAllocation(int|float $amount): SendLimitData
ChipSend::listSendLimits(array $filters = []): array
ChipSend::resendApprovalRequest(int $id): array

// Groups
ChipSend::createGroup(array $data): array
ChipSend::getGroup(int $id): array
ChipSend::listGroups(array $filters = []): array
ChipSend::updateGroup(int $id, array $data): array
ChipSend::deleteGroup(int $id): void

// Accounts
ChipSend::listAccounts(): array

// Webhooks
ChipSend::createSendWebhook(array $data): SendWebhookData
ChipSend::getSendWebhook(int $id): SendWebhookData
ChipSend::listSendWebhooks(array $filters = []): array
ChipSend::updateSendWebhook(int $id, array $data): SendWebhookData
ChipSend::deleteSendWebhook(int $id): void
```

## PurchaseBuilder

```php
Chip::purchase()
    ->brand(string $brandId): self
    ->currency(string $currency): self
    ->customer(string $email, ?string $fullName, ?string $phone, ?string $country): self
    ->email(string $email): self
    ->clientId(string $clientId): self
    ->billingAddress(string $street, string $city, string $zip, ?string $state = null, ?string $country = null): self
    ->shippingAddress(string $street, string $city, string $zip, ?string $state = null, ?string $country = null): self
    ->addProductCents(string $name, int $price, string|float|int $quantity = 1, int $discount = 0, float|string $taxPercent = 0, ?string $category = null, ?int $totalPriceOverride = null): self
    ->addProductMoney(string $name, Money $price, string|float|int $quantity = 1, ?Money $discount = null, float|string $taxPercent = 0, ?string $category = null, ?int $totalPriceOverride = null): self
    ->addProductObject(ProductData $product): self
    ->addLineItem(LineItemInterface $item): self
    ->fromCheckoutable(CheckoutableInterface $checkoutable): self
    ->fromCustomer(CustomerInterface $customer): self
    ->reference(string $reference): self
    ->idempotencyKey(string $idempotencyKey): self
    ->successUrl(string $url): self
    ->failureUrl(string $url): self
    ->cancelUrl(string $url): self
    ->redirects(string $success, ?string $failure, ?string $cancel): self
    ->webhook(string $url): self
    ->sendReceipt(bool $send = true): self
    ->preAuthorize(bool $skipCapture = true): self
    ->forceRecurring(bool $force = true): self
    ->due(int $timestamp, bool $strict = false): self
    ->discount(int $amount): self
    ->notes(string $notes): self
    ->metadata(array $metadata): self
    ->issued(string $date): self
    ->paymentMethodWhitelist(array $methods): self
    ->tags(array $tags): self
    ->language(string $language): self
    ->debt(int $cents): self
    ->timezone(string $timezone): self
    ->emailMessage(string $message): self
    ->requestClientDetails(array $fields): self
    ->cc(array $emails): self
    ->bcc(array $emails): self
    ->legalName(string $legalName): self
    ->brandName(string $brandName): self
    ->registrationNumber(string $registrationNumber): self
    ->taxNumber(string $taxNumber): self
    ->bankAccount(string $bankAccount): self
    ->bankCode(string $bankCode): self
    ->toArray(): array
    ->create(): PurchaseData
    ->save(): PurchaseData
```

## Data Objects

### Purchase

```php
$purchase->id: string
$purchase->status: string
$purchase->checkout_url: ?string
$purchase->reference: ?string
$purchase->client: ClientDetailsData
$purchase->purchase: PurchaseDetailsData
$purchase->payment: ?PaymentData

$purchase->getAmount(): Money
$purchase->getAmountInCents(): int
$purchase->getCurrency(): string
$purchase->getCheckoutUrl(): ?string
$purchase->getClientId(): ?string
$purchase->getMetadata(): ?array
$purchase->isRecurring(): bool
$purchase->isPaid(): bool
$purchase->isRefunded(): bool
$purchase->isCancelled(): bool
$purchase->isOnHold(): bool
$purchase->isPending(): bool
$purchase->hasError(): bool
$purchase->canBeRefunded(): bool
$purchase->getRefundableAmount(): Money
$purchase->getCreatedAt(): CarbonImmutable
$purchase->getUpdatedAt(): CarbonImmutable
```

### Product

```php
$product->name: string
$product->quantity: string
$product->price: Money
$product->discount: Money
$product->tax_percent: float|string
$product->category: ?string
$product->total_price_override: ?int

$product->getCurrency(): string
$product->getPriceInCents(): int
$product->getDiscountInCents(): int
$product->getSubtotalInCents(): int
$product->getDiscountTotalInCents(): int
$product->getTotalPrice(): Money
$product->getTotalPriceInCents(): int
```

Construct with `ProductData::from(...)` or `make(...)`. Two serializers, different contracts: `toArray()` is the full local shape and emits `'category' => null` when unset (plus `discount: 0`, `tax_percent: 0.0`); `toRequestArray()` is the CHIP wire shape — it omits null fields but keeps zeros. CHIP request sites must use `toRequestArray()` (the spec types `category` as non-nullable `string`). `total_price_override` (`?int`, cents) overrides the line total when non-null — zero is emittable. `tax_percent` accepts a float or numeric string outbound and emits as given; responses parse to float.

### Payment

```php
$payment->amount: Money
$payment->net_amount: Money
$payment->fee_amount: Money
$payment->pending_amount: Money
$payment->payment_type: string
$payment->is_outgoing: bool

$payment->getAmountInCents(): int
$payment->getNetAmountInCents(): int
$payment->getFeeAmountInCents(): int
$payment->getCurrency(): string
$payment->isPaid(): bool
$payment->getPaidAt(): ?CarbonImmutable
$payment->getRelatedPurchaseId(): ?string
$payment->getReference(): ?string
```

`Chip::refundPurchase()` returns a `PaymentData` for completed refunds. When CHIP is still processing the refund, it returns a `PurchaseData` with `status = pending_refund` until the later `payment.refunded` webhook arrives.

### SendInstruction

```php
$instruction->id: int
$instruction->bank_account_id: int
$instruction->amount: string
$instruction->state: string
$instruction->email: string
$instruction->description: string
$instruction->reference: string
$instruction->send_recipient_receipt: bool
$instruction->receipt_url: ?string

$instruction->getAmountInMinorUnits(): int
$instruction->isReceived(): bool
$instruction->isEnquiring(): bool
$instruction->isExecuting(): bool
$instruction->isReviewing(): bool
$instruction->isAccepted(): bool
$instruction->isCompleted(): bool
$instruction->isRejected(): bool
$instruction->isDeleted(): bool
$instruction->isPending(): bool
```

### BankAccount

```php
$account->id: int
$account->bank_code: string
$account->account_number: string
$account->name: string
$account->status: string
$account->reference: ?string
```

## Actions

### DispatchChipWebhookAction

```php
use AIArmada\Chip\Actions\DispatchChipWebhookAction;

$action = app(DispatchChipWebhookAction::class);

$action->execute(string $event, array $payload, ?Model $owner = null): WebhookResult
```

Dispatches a webhook event through the `WebhookRouter` with optional owner scoping. Returns a `WebhookResult` with `wasHandled(): bool` and `wasSkipped(): bool`.

### SendWebhookReceived

```php
use AIArmada\Chip\Events\SendWebhookReceived;

SendWebhookReceived::$payload: array<string, mixed>
```

The dedicated CHIP Send webhook route verifies the raw request with the Send webhook's SHA-512 RSA signature and dispatches the verified JSON object unchanged. The payload shape depends on the configured Send `event_hooks` value; no synthetic Send status event is created by the package.

### SyncChipRecordsFromApiAction

```php
use AIArmada\Chip\Actions\SyncChipRecordsFromApiAction;

$action = app(SyncChipRecordsFromApiAction::class);

$action->handle(
    array $purchaseIds,
    bool $dryRun = false,
    bool $overwriteExisting = false,
    array $statuses = [],
    ?callable $onProgress = null,
): array{processed: int, synced: int, skipped: int, failed: int, errors: array<int, string>}
```

Fetches CHIP purchases from the remote API and stores them locally via `StoreWebhookData`. It does not reach into checkout or customer packages; downstream subscribers can consume the stable CHIP webhook event payload instead. Supports dry-run mode and status filtering.

## Support Classes

### ChipOwnerTuple

```php
use AIArmada\Chip\Support\ChipOwnerTuple;

ChipOwnerTuple::extractFromPayload(array $payload): ?array{string, string}
ChipOwnerTuple::resolveFromPayload(array $payload): ?Model
ChipOwnerTuple::embedInPayload(array $payload, Model $owner): array
```

Utility for embedding and extracting the owner tuple (`__owner_type`, `__owner_id`) in CHIP webhook payload envelopes. Used during webhook dispatch to carry the owner context through the async processing pipeline so owner-aware listeners can restore it on the worker side.

### ChipPaymentStatusMapper

```php
use AIArmada\Chip\Support\ChipPaymentStatusMapper;

ChipPaymentStatusMapper::map(string $chipStatus): PaymentStatus
ChipPaymentStatusMapper::mapWebhook(?string $chipStatus, string $eventType): PaymentStatus
```

Maps CHIP-internal status strings and webhook event types to the standardized `AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus` enum used by the unified gateway interface. Recognized webhook event types take precedence over the payload status.

### ChipWebhookOwnerResolver

```php
use AIArmada\Chip\Support\ChipWebhookOwnerResolver;

ChipWebhookOwnerResolver::resolveFromPayload(array $payload): ?Model
ChipWebhookOwnerResolver::resolveFromBrandId(string $brandId): ?Model
```

Resolves the owner model from a webhook payload's `brand_id` field using the `chip.owner.webhook_brand_id_map` config. Supports both top-level `brand_id` and nested `purchase.brand_id` payload shapes.

### ResolveWebhookPurchaseId

```php
use AIArmada\Chip\Support\ResolveWebhookPurchaseId;

ResolveWebhookPurchaseId::fromPaymentPayload(array $payload): ?string
ResolveWebhookPurchaseId::fromAnyPayload(array $payload): ?string
```

Extracts the CHIP purchase ID from webhook payloads. `fromPaymentPayload` handles payment-shaped refund completion payloads (`related_to.id`), while `fromAnyPayload` falls back to the top-level `id` or `data.id` field.

### WebhookOwnerBatchRunner

```php
use AIArmada\Chip\Support\WebhookOwnerBatchRunner;

$runner = app(WebhookOwnerBatchRunner::class);

$runner->run(callable $callback, int $limit = 0): array{processed: int, succeeded: int, failed: int}
```

Iterates over distinct owner tuples from the webhooks table, running the callback inside `OwnerContext::withOwner()` for each. Used by batch commands to process webhooks per-tenant when owner mode is enabled. When a current owner context is already active, runs the callback once in that context instead.

## Conventions

- Amounts in **cents** (sen) for API communication
- Use `Money` objects for type-safe calculations
- Timestamps as Unix epoch or ISO8601
- Omit optional fields rather than send empty strings
- `Retry-After`: numeric seconds, or HTTP-date (IMF-fixdate / RFC 850 / asctime with matching weekday; two-digit years more than 50 years out take the past century). Missing or malformed → 60s default; past dates → 0. See [Retry protocol](09-webhooks.md#retry-protocol).
- `__all__` errors: object (`{"message", "code"}`) on generic 400s, first-of-list on charge errors, bare string entry is message-only. The extracted code merges only when no top-level code exists. See [Errors](04-usage.md#errors).
- Field bounds (enforced before any request): `tax_percent` 0–100 (numeric); `notes` ≤10000, `language` ≤2, `email_message` ≤512 chars; product `name`/`category` ≤256; client `legal_name` ≤1000, `brand_name` ≤128, `registration_number`/`tax_number`/`bank_code` ≤32, `bank_account` ≤64, `cc`/`bcc` members ≤254; `platform` ∈ {web, api, ios, android, macos, windows}; `creator_agent` ≤32. Explicit nulls rejected on `platform`/`creator_agent`.
- `paymentMethodWhitelist()` validates members locally and throws on unknown values; the `createCheckoutPurchase()` options/config path forwards its whitelist unvalidated (a typo'd `chip.defaults.payment_method_whitelist` fails at CHIP, not locally). Two paths, one wire key, divergent validation.
