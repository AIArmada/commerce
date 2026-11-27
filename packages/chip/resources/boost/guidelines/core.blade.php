@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## CHIP

@verbatim
- Use the CHIP Collect and Send transport contracts for external API implementations; bind drivers in ChipServiceProvider.
- Resolve `Features\Collect\Api\PurchaseOperations` and `ClientOperations` for remote purchase and client operations. Resolve `Features\Statements\Api\AccountOperations` for balance, turnover and statements, and `Features\WebhookDelivery\Api\WebhookRegistrationOperations` for remote webhook registration. Do not reintroduce pass-through purchase actions.
- Payout creation, bank account creation/deletion, and webhook resend run through owner-checked core Payouts actions.
- Purchase reservations with unknown remote outcomes stay blocked even after the TTL. Only release a specific reservation after confirming remotely that the purchase was not created.
- Idempotency keys must remain stable across retries. A fingerprint mismatch fails closed; do not silently retry with a new key.

```php
use AIArmada\Chip\Features\Payouts\Actions\CreateSendInstruction;

app(CreateSendInstruction::class)->handle('10.29', $bankAccountId, $description, $reference, $email);
```
@endverbatim
@endif
