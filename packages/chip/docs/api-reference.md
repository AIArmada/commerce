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

## Conventions

- Amounts in **cents** (sen) for API communication
- Use `Money` objects for type-safe calculations
- Timestamps as Unix epoch or ISO8601
- Omit optional fields rather than send empty strings
