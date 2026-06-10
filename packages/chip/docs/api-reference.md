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
checksum: {hmac_sha256(epoch, CHIP_SEND_API_SECRET)}
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
Chip::createPurchase(array $data): PurchaseData
Chip::getPurchase(string $id): PurchaseData
Chip::cancelPurchase(string $id): PurchaseData
Chip::refundPurchase(string $id, ?int $amount = null): PaymentData|PurchaseData
Chip::capturePurchase(string $id, ?int $amount = null): PurchaseData
Chip::releasePurchase(string $id): PurchaseData
Chip::markPurchaseAsPaid(string $id, ?int $paidOn = null): PurchaseData
Chip::resendInvoice(string $id): PurchaseData
Chip::deleteRecurringToken(string $id): PurchaseData
Chip::getPaymentMethods(array $filters = []): array

// Clients
Chip::createClient(array $data): ClientData
Chip::getClient(string $id): ClientData
Chip::listClients(array $filters = []): array
Chip::updateClient(string $id, array $data): ClientData
Chip::partialUpdateClient(string $id, array $data): ClientData
Chip::deleteClient(string $id): void

// Account
Chip::getAccountBalance(): array
Chip::getAccountTurnover(array $filters = []): array
Chip::listCompanyStatements(array $filters = []): array
Chip::getCompanyStatement(string $id): CompanyStatementData
Chip::cancelCompanyStatement(string $id): CompanyStatementData

// Webhooks
Chip::createWebhook(array $data): array
Chip::getWebhook(string $id): array
Chip::updateWebhook(string $id, array $data): array
Chip::deleteWebhook(string $id): void
Chip::listWebhooks(array $filters = []): array

// Public Key
Chip::getPublicKey(): string
Chip::getBrandId(): string
```

## ChipSendService (ChipSend Facade)

```php
use AIArmada\Chip\Facades\ChipSend;

// Send Instructions
ChipSend::createSendInstruction(
    int $amountInCents,
    string $currency,
    string $recipientBankAccountId,
    string $description,
    string $reference,
    string $email
): SendInstructionData

ChipSend::getSendInstruction(string $id): SendInstructionData
ChipSend::listSendInstructions(array $filters = []): array
ChipSend::cancelSendInstruction(string $id): SendInstructionData
ChipSend::deleteSendInstruction(string $id): void
ChipSend::resendSendInstructionWebhook(string $id): array

// Bank Accounts
ChipSend::createBankAccount(
    string $bankCode,
    string $accountNumber,
    string $accountHolderName,
    ?string $reference = null
): BankAccountData

ChipSend::getBankAccount(string $id): BankAccountData
ChipSend::listBankAccounts(array $filters = []): array
ChipSend::updateBankAccount(string $id, array $data): BankAccountData
ChipSend::deleteBankAccount(string $id): void
ChipSend::resendBankAccountWebhook(string $id): array

// Send Limits
ChipSend::getSendLimit(int|string $id): SendLimitData

// Groups
ChipSend::createGroup(array $data): array
ChipSend::getGroup(string $id): array
ChipSend::listGroups(array $filters = []): array
ChipSend::updateGroup(string $id, array $data): array
ChipSend::deleteGroup(string $id): void

// Accounts
ChipSend::listAccounts(): array

// Webhooks
ChipSend::createSendWebhook(array $data): SendWebhookData
ChipSend::getSendWebhook(string $id): SendWebhookData
ChipSend::listSendWebhooks(array $filters = []): array
ChipSend::updateSendWebhook(string $id, array $data): SendWebhookData
ChipSend::deleteSendWebhook(string $id): void
```

## PurchaseBuilder

```php
Chip::purchase()
    ->brand(string $brandId): self
    ->currency(string $currency = 'MYR'): self
    ->customer(string $email, ?string $fullName, ?string $phone, ?string $country): self
    ->email(string $email): self
    ->clientId(string $clientId): self
    ->billingAddress(string $street, string $city, string $zip, ?string $state, ?string $country): self
    ->shippingAddress(string $street, string $city, string $zip, ?string $state, ?string $country): self
    ->addProductCents(string $name, int $price, int $quantity = 1, int $discount = 0, float $taxPercent = 0): self
    ->addProductMoney(string $name, Money $price, int $quantity = 1, ?Money $discount = null, float $taxPercent = 0): self
    ->addProductObject(ProductData $product): self
    ->addLineItem(LineItemInterface $item): self
    ->fromCheckoutable(CheckoutableInterface $checkoutable): self
    ->fromCustomer(CustomerInterface $customer): self
    ->reference(string $reference): self
    ->successUrl(string $url): self
    ->failureUrl(string $url): self
    ->cancelUrl(string $url): self
    ->redirects(string $success, ?string $failure, ?string $cancel): self
    ->webhook(string $url): self
    ->sendReceipt(bool $send = true): self
    ->preAuthorize(bool $skipCapture = true): self
    ->forceRecurring(bool $force = true): self
    ->due(int $timestamp, bool $strict = false): self
    ->notes(string $notes): self
    ->metadata(array $metadata): self
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
$account->id: string
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

### HandleSendInstructionWebhookAction

```php
use AIArmada\Chip\Actions\HandleSendInstructionWebhookAction;

$action = app(HandleSendInstructionWebhookAction::class);

$action->execute(
    EnrichedWebhookPayload $payload,
    SendInstructionState $targetState,
    string $eventClass,
    array $eventArgs = [],
): WebhookResult
```

Updates the local send instruction state from a webhook payload and dispatches the typed event. Returns `WebhookResult::skipped()` when the send instruction is not found locally.

### RunChipPurchaseDocGenerationAction

```php
use AIArmada\Chip\Actions\RunChipPurchaseDocGenerationAction;

$action = app(RunChipPurchaseDocGenerationAction::class);

$action->execute(string $purchaseId, array $payload, DocData $docData, string $docTypeConfigKey): void
```

Generates a document (invoice/credit note) from a CHIP payment event. Guards against duplicates per payment ID. No-ops when `aiarmada/docs` is not installed.

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

Fetches CHIP purchases from the remote API and stores them locally via `StoreWebhookData`. Optionally links checkout customers via `ChipCustomerBridge`. Supports dry-run mode and status filtering.

## Support Classes

### ChipCustomerBridge

```php
use AIArmada\Chip\Support\ChipCustomerBridge;

$bridge = app(ChipCustomerBridge::class);

$bridge->findCheckoutSessionByPaymentId(string $paymentId): ?Model
$bridge->linkCustomer(Model $checkoutSession, array $payload, string $source = 'chip_customer_bridge'): void
```

Integrates with `aiarmada/checkout` and `aiarmada/customers` to link a CHIP client ID to a local customer model after a completed checkout session. The checkout session model and customer model classes are configurable via `chip.integrations.customer_bridge.*`.

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
```

Maps CHIP-internal status strings to the standardized `AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus` enum used by the unified gateway interface.

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

### BuildChipDocData

```php
use AIArmada\Chip\Support\BuildChipDocData;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Models\Doc;

$builder = app(BuildChipDocData::class);

$builder->forPayment(Purchase $purchase, PurchasePaid $event, DocType $docType): DocData
$builder->forRefund(Purchase $purchase, PaymentRefunded $event, DocType $docType, ?Doc $originalInvoice): DocData
```

Builds `DocData` objects from CHIP payment and refund events for use with `RunChipPurchaseDocGenerationAction`. Extracts customer data, line items, and metadata from the purchase and event data.

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
