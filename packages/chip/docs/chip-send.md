---
title: CHIP Send
---

# CHIP Send

Disbursements, vendor payouts, and transfers.

## Facade

```php
use AIArmada\Chip\Facades\ChipSend;
```

## Send Instructions

### Create Disbursement

```php
$instruction = ChipSend::createSendInstruction(
    amountInCents: 10000,
    currency: 'MYR',
    recipientBankAccountId: 'bank_abc123',
    description: 'Affiliate Commission',
    reference: 'AFF-2025-001',
    email: 'affiliate@example.com',
);

$instruction->id;
$instruction->state;        // 'received', 'completed', etc.
$instruction->amount;       // '100.00'
$instruction->receipt_url;
```

### Retrieve

```php
$instruction = ChipSend::getSendInstruction('inst_abc123');

$instruction->isCompleted();
$instruction->isPending();
$instruction->isRejected();
$instruction->getAmountInMinorUnits(); // cents
```

### List

```php
$instructions = ChipSend::listSendInstructions([
    'state' => 'completed',
    'created_after' => '2025-01-01',
]);
```

### Cancel

```php
$instruction = ChipSend::cancelSendInstruction('inst_abc123');
```

### Delete

```php
ChipSend::deleteSendInstruction('inst_abc123');
```

### Resend Webhook

```php
ChipSend::resendSendInstructionWebhook('inst_abc123');
```

## Bank Accounts

### Create

```php
$account = ChipSend::createBankAccount(
    bankCode: 'MBBEMYKL',
    accountNumber: '1234567890',
    accountHolderName: 'John Doe',
    reference: 'vendor-001',
);

$account->id;
$account->status; // 'pending', 'verified', 'rejected'
```

### Retrieve

```php
$account = ChipSend::getBankAccount('bank_abc123');
```

### List

```php
$accounts = ChipSend::listBankAccounts([
    'status' => 'verified',
    'group_id' => 'grp_123',
]);
```

### Update

```php
$account = ChipSend::updateBankAccount('bank_abc123', [
    'reference' => 'new-reference',
]);
```

### Delete

```php
ChipSend::deleteBankAccount('bank_abc123');
```

### Resend Webhook

```php
ChipSend::resendBankAccountWebhook('bank_abc123');
```

## Send Limits

```php
$limit = ChipSend::getSendLimit($limitId);

$limit->amount;       // cents
$limit->fee;          // cents
$limit->net_amount;   // cents
$limit->currency;
$limit->status;
```

## Groups

Organize bank accounts into groups.

```php
// Create
$group = ChipSend::createGroup([
    'name' => 'Vendors',
    'description' => 'Vendor payouts',
]);

// Retrieve
$group = ChipSend::getGroup('grp_abc123');

// List
$groups = ChipSend::listGroups();

// Update
$group = ChipSend::updateGroup('grp_abc123', ['name' => 'Updated']);

// Delete
ChipSend::deleteGroup('grp_abc123');
```

## Accounts

List payout accounts linked to merchant.

```php
$accounts = ChipSend::listAccounts();
```

## Webhooks

### Create

```php
$webhook = ChipSend::createSendWebhook([
    'url' => 'https://example.com/webhooks/chip-send',
    'events' => ['send_instruction.completed'],
]);
```

### Retrieve

```php
$webhook = ChipSend::getSendWebhook('wh_abc123');
```

### List

```php
$webhooks = ChipSend::listSendWebhooks();
```

### Update

```php
$webhook = ChipSend::updateSendWebhook('wh_abc123', [
    'events' => ['send_instruction.completed', 'bank_account.verified'],
]);
```

### Delete

```php
ChipSend::deleteSendWebhook('wh_abc123');
```

## Instruction States

| State | Description |
|-------|-------------|
| `received` | Instruction received |
| `enquiring` | Verifying bank account |
| `executing` | Processing transfer |
| `reviewing` | Manual review |
| `accepted` | Accepted, pending completion |
| `completed` | Successfully transferred |
| `rejected` | Transfer failed |
| `deleted` | Instruction deleted |

## Next Steps

- [Webhooks](webhooks.md) – Handle Send events
- [API Reference](api-reference.md) – Complete methods
