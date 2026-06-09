# Testing Fakes

Two fakes are available for testing CHIP billing code.

## FakeChipClient

Low-level HTTP API faking. Mocks raw CHIP API responses as plain arrays. Use when testing HTTP-level concerns — endpoint contracts, response parsing, error handling, or scenarios where you need fine-grained control over individual API responses.

```php
$client = new FakeChipClient('test-brand-id');
$client->setPurchaseResponse(['id' => 'purchase_123', 'status' => 'paid']);
```

**Canonical data store.** `FakeChipCollectService` wraps this class.

## FakeChipCollectService

High-level business-logic faking. Drop-in replacement for `ChipCollectService` returning typed DTOs (PurchaseData, ClientData, etc.). Use when testing code that depends on `ChipCollectService` — subscription management, payment flows, or any orchestration that calls the service.

```php
$this->instance(ChipCollectService::class, new FakeChipCollectService);
```

**Canonical test double.** Wraps `FakeChipClient` and matches the real service interface.

## When to use which

| You are testing | Use |
|---|---|
| HTTP-level CHIP API interactions | `FakeChipClient` |
| Business logic depending on ChipCollectService | `FakeChipCollectService` |
| Custom CHIP integrations or edge cases | `FakeChipClient` (more control) |
| Standard billing flows (subscriptions, payments) | `FakeChipCollectService` (typed DTOs) |

Both fakes share the same underlying data store (`FakeChipClient`'s internal state). Prefer `FakeChipCollectService` unless you need raw API-level control.
