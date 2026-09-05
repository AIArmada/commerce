---
title: CHIP Send
---

# CHIP Send

CHIP Send is CHIP's disbursement API for sending money to verified bank accounts. It is separate from CHIP Collect: it has different base URLs, HMAC request authentication, integer resource IDs, and its own webhook API.

## Authentication and environments

The package uses the official CHIP Send endpoints:

| Environment | Base URL |
|-------------|----------|
| Sandbox | `https://staging-api.chip-in.asia/api` |
| Production | `https://api.chip-in.asia/api` |

Every request uses `Authorization: Bearer {API_KEY}`, an `epoch` header, and a hexadecimal HMAC-SHA512 `checksum`. The signed value is the epoch immediately followed by the API key, using the API secret as the HMAC key. The epoch must be recent according to CHIP's API rules.

```dotenv
CHIP_ENVIRONMENT=sandbox
CHIP_SEND_API_KEY=your-send-api-key
CHIP_SEND_API_SECRET=your-send-api-secret
```

## Send instructions

### Create

The public package method accepts cents and converts them to CHIP's documented decimal-string amount. CHIP Send does not accept a currency field on this request; the currency is determined by the Send account.

```php
use AIArmada\Chip\Facades\ChipSend;

$instruction = ChipSend::createSendInstruction(
    amountInCents: 10000,
    recipientBankAccountId: 1,
    description: 'Affiliate commission',
    reference: 'AFF-2025-001',
    email: 'affiliate@example.com',
    sendRecipientReceipt: true,
);

$instruction->id;       // integer
$instruction->state;    // received, enquiring, executing, reviewing, accepted, completed, rejected, or deleted
$instruction->amount;   // decimal string, for example '100.00'
$instruction->amountMoney; // Money value in the configured Send currency
```

The request maps to `POST /send/send_instructions` with `bank_account_id`, `amount`, `email`, `description`, `reference`, and the optional `send_recipient_receipt` field.

### Retrieve, list, and delete

```php
$instruction = ChipSend::getSendInstruction(1);

$instructions = ChipSend::listSendInstructions([
    'state' => 'completed',
]);

ChipSend::deleteSendInstruction(1);
```

Deletion maps to CHIP's `DELETE /send/send_instructions/{id}` operation and is only available while the instruction is unprocessed. To ask CHIP to resend the latest instruction webhook:

```php
ChipSend::resendSendInstructionWebhook(1);
```

That maps to `POST /send/send_instructions/{id}/resend_webhook_event`.

## Bank accounts

```php
$account = ChipSend::createBankAccount(
    bankCode: 'MBBEMYKL',
    accountNumber: '1234567890',
    accountHolderName: 'John Doe',
    reference: 'vendor-001',
);

$account->id;       // integer
$account->status;   // pending, verified, or rejected

$account = ChipSend::getBankAccount(1);
$accounts = ChipSend::listBankAccounts(['status' => 'verified']);

ChipSend::deleteBankAccount(1);
ChipSend::resendBankAccountWebhook(1);
```

The create operation maps to `POST /send/bank_accounts`. CHIP's documented Send API exposes create, retrieve, list, delete, and webhook-resend operations for bank accounts; it does not expose a bank-account update operation, so this package does not provide one.

## Send limits

Send-limit amounts are expressed by CHIP in major currency units (`100` means RM100), not cents:

```php
$limit = ChipSend::getSendLimit(1);
$limit = ChipSend::increaseBudgetAllocation(1000);
$limits = ChipSend::listSendLimits();

ChipSend::resendApprovalRequest(1);

$limit->amount;      // major units
$limit->fee;         // major units
$limit->net_amount;  // major units
$limit->amountMoney; // exact minor-unit Money value
$limit->feeMoney;    // exact minor-unit Money value
$limit->netAmountMoney; // exact minor-unit Money value
```

These map to `GET /send/send_limits/{id}`, `POST /send/send_limits`, `GET /send/send_limits`, and `POST /send/send_limits/{id}/resend_approval_requests`.

## Groups and accounts

```php
$group = ChipSend::createGroup(['name' => 'Vendors']);
$group = ChipSend::getGroup(1);
$groups = ChipSend::listGroups();
$group = ChipSend::updateGroup(1, ['name' => 'Preferred vendors']);
ChipSend::deleteGroup(1);

$accounts = ChipSend::listAccounts();
```

Group updates use the documented `PATCH /send/groups/{id}` operation.

## Send webhooks

Send webhooks are managed at the API root (`/webhooks`), not under `/send/webhooks`:

```php
$webhook = ChipSend::createSendWebhook([
    'name' => 'Commerce Send webhook',
    'callback_url' => 'https://example.com/chip/send/webhooks',
    'email' => 'ops@example.com',
    'event_hooks' => [
        'send_instruction_status',
    ],
]);

$webhook = ChipSend::getSendWebhook(1);
$webhooks = ChipSend::listSendWebhooks();
$webhook = ChipSend::updateSendWebhook(1, [
    'event_hooks' => ['bank_account_status', 'budget_allocation_status'],
]);
ChipSend::deleteSendWebhook(1);
```

The documented hook categories are:

- `bank_account_status`
- `budget_allocation_status`
- `send_instruction_status`

The package's Send route verifies `X-Signature` as a base64 RSA PKCS#1 v1.5 SHA-512 signature over the raw request body using the dedicated Send webhook public key. It then dispatches `AIArmada\Chip\Events\SendWebhookReceived` with the verified JSON object. The payload is not converted into synthetic status events; applications interpret it according to the configured hook category.

Configure the route and key lookup as follows:

```dotenv
CHIP_SEND_WEBHOOK_ROUTE=/chip/send/webhooks
CHIP_SEND_WEBHOOK_ID=1
CHIP_SEND_WEBHOOK_PUBLIC_KEYS='{"1":"-----BEGIN PUBLIC KEY-----\\n...\\n-----END PUBLIC KEY-----"}'
```

Use `CHIP_SEND_WEBHOOK_ID` when the package should retrieve the public key from CHIP. Use the JSON key map when several Send webhooks are configured locally. The route returns HTTP 200 only after verification and JSON-object validation, allowing CHIP's documented retry protocol to handle failures.

## Official references

- [CHIP Send API introduction](https://docs.chip-in.asia/chip-send/api-reference/introduction)
- [Create send instruction](https://docs.chip-in.asia/chip-send/api-reference/send-instructions/create)
- [Send webhook validation](https://docs.chip-in.asia/chip-send/api-reference/webhooks/validation)
- [Send webhook delivery protocol](https://docs.chip-in.asia/chip-send/api-reference/webhooks/delivery-protocol)
