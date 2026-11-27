@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Filament CHIP

@verbatim
- This package adapts CHIP Collect and Send data to Filament v5. It does not own payment or payout tables.
- Delegate bank account and payout creation to CHIP Payouts actions. Never persist a second local record after a successful remote creation.
- Apply server-side owner scoping to resources, exports, actions and widgets. Only verified, active crediting bank accounts belonging to the current owner may receive payouts.
- Never expose raw provider errors, recurring tokens, signed checkout URLs or full account numbers to unauthorized users.

```php
use AIArmada\Chip\Features\Payouts\Actions\CreateSendInstruction;

app(CreateSendInstruction::class)->handle('10.29', $bankAccountId, $description, $reference, $email);
```
@endverbatim
@endif
