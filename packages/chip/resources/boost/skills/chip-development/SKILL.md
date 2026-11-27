---
name: chip-development
description: Develop CHIP integrations using Laravel Dune feature boundaries and secure payment operations.
---

# CHIP development

- Use the CHIP Collect and Send transport contracts for external API implementations; bind drivers in ChipServiceProvider.
- Resolve `Features\Collect\Api\PurchaseOperations` and `ClientOperations` for remote purchase and client operations. Resolve `Features\Statements\Api\AccountOperations` for balance, turnover and statements, and `Features\WebhookDelivery\Api\WebhookRegistrationOperations` for remote webhook registration. Do not reintroduce pass-through purchase actions.
- Payout creation, bank account creation/deletion, and webhook resend run through owner-checked core Payouts actions.
- Purchase reservations with unknown remote outcomes stay blocked even after the TTL. Only release a specific reservation after confirming remotely that the purchase was not created.
- Idempotency keys must remain stable across retries. A fingerprint mismatch fails closed; do not silently retry with a new key.

```php
use AIArmada\Chip\Features\Payouts\Actions\CreateSendInstruction;

app(CreateSendInstruction::class)->handle('10.29', $bankAccountId, $description, $reference, $email);
```

## Verification

From the commerce monorepo root:

```sh
composer --working-dir=packages/chip arch:check
./vendor/bin/pint --test packages/chip
./vendor/bin/pest tests/src/Chip
```
