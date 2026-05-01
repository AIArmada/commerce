---
title: Multitenancy
---

import Aside from "@components/Aside.astro"

# Multitenancy

The CHIP package supports multi-tenant architectures using the `commerce-support` owner scoping system, allowing purchases and payments to be isolated by tenant (merchant, store, organisation).

## Enabling Owner Mode

```php
// config/chip.php
'owner' => [
    'enabled' => env('CHIP_OWNER_ENABLED', false),
    'include_global' => env('CHIP_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('CHIP_OWNER_AUTO_ASSIGN', true),
    'webhook_brand_id_map' => [],
],
```

```env
CHIP_OWNER_ENABLED=true
```

<Aside variant="warning">
  The default is `false` (single-tenant). Without enabling this, all tenants share the same CHIP purchase and payment records. Always set `CHIP_OWNER_ENABLED=true` in multi-tenant deployments.
</Aside>

## Binding the Owner Resolver

Bind `OwnerResolverInterface` in `AppServiceProvider::register()`:

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

$this->app->bind(OwnerResolverInterface::class, function () {
    return new class implements OwnerResolverInterface {
        public function resolve(): ?\Illuminate\Database\Eloquent\Model
        {
            return auth()->user()?->currentMerchant;
        }
    };
});
```

## How It Works

When `owner.enabled` is `true`:

1. `ChipPurchase` and `ChipPayment` queries are automatically scoped to the resolved owner
2. New records get `owner_type` / `owner_id` set automatically
3. If the owner cannot be resolved, queries fail closed (return zero rows)
4. Webhook handlers resolve the owner from `webhook_brand_id_map` if configured

## Owner-Scoped Models

| Model | Owner Columns |
|-------|--------------|
| `ChipPurchase` | `owner_type`, `owner_id` |
| `ChipPayment` | `owner_type`, `owner_id` |
| `ChipSendInstruction` | `owner_type`, `owner_id` |

## Webhook Brand ID Mapping

In multi-tenant setups where each tenant has their own CHIP Brand ID, map brand IDs to owners:

```php
// config/chip.php
'owner' => [
    'enabled' => true,
    'webhook_brand_id_map' => [
        'brand-uuid-tenant-a' => ['type' => App\Models\Merchant::class, 'id' => 'merchant-a-uuid'],
        'brand-uuid-tenant-b' => ['type' => App\Models\Merchant::class, 'id' => 'merchant-b-uuid'],
    ],
],
```

When a webhook arrives, the package resolves the owner from the `brand_id` in the payload and processes it in that owner's context.

## Querying with Owner Scope

```php
use AIArmada\Chip\Models\ChipPurchase;

// Automatically scoped (global scope applied)
$purchases = ChipPurchase::query()->get();

// Explicit owner
$purchases = ChipPurchase::forOwner($merchant)->get();

// Include global records
$purchases = ChipPurchase::forOwner($merchant, includeGlobal: true)->get();
```

## Background Commands

Commands iterate all owners and process each in scope:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

class RetryWebhooksCommand extends Command
{
    public function handle(): void
    {
        // Enumerate all distinct owners without scope
        $owners = ChipPurchase::withoutOwnerScope()
            ->select('owner_type', 'owner_id')
            ->distinct()
            ->get();

        foreach ($owners as $row) {
            $owner = $row->owner_type::find($row->owner_id);

            OwnerContext::withOwner($owner, function () use ($owner): void {
                // Process owner-scoped command work for this owner only
            });
        }
    }
}
```

## Testing

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

it('scopes chip purchases to owner', function () {
    config(['chip.owner.enabled' => true]);

    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();

    app()->instance(OwnerResolverInterface::class, new class($merchantA) implements OwnerResolverInterface {
        public function __construct(private \Illuminate\Database\Eloquent\Model $owner) {}
        public function resolve(): ?\Illuminate\Database\Eloquent\Model { return $this->owner; }
    });

    ChipPurchase::factory()->create(['owner_type' => $merchantA->getMorphClass(), 'owner_id' => $merchantA->id]);
    ChipPurchase::factory()->create(['owner_type' => $merchantB->getMorphClass(), 'owner_id' => $merchantB->id]);

    expect(ChipPurchase::query()->count())->toBe(1);
});
```
