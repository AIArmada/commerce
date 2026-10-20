---
title: Storage
---

# Storage

The Cart package ships with database storage and exposes a `StorageInterface`
for applications that want to bind a custom backend.

## Storage Interface

The shipped `DatabaseStorage` implements `StorageInterface`:

```php
interface StorageInterface
{
    // Owner scoping
    public function withOwner(?Model $owner): static;
    public function getOwnerType(): ?string;
    public function getOwnerId(): string|int|null;

    // Cart operations
    public function has(string $identifier, string $instance): bool;
    public function forget(string $identifier, string $instance): void;
    public function flush(): void;

    // Revisioned reads and writes
    public function getSnapshot(string $identifier, string $instance): ?CartStorageSnapshot;
    public function putItems(string $identifier, string $instance, array $items, CartRevision $expectedRevision): CartRevision;
    public function putConditions(string $identifier, string $instance, array $conditions, CartRevision $expectedRevision): CartRevision;
    public function putBoth(string $identifier, string $instance, array $items, array $conditions, CartRevision $expectedRevision): CartRevision;
    public function forgetIfUnchanged(string $identifier, string $instance, CartRevision $expectedRevision): bool;
    public function clearAllIfUnchanged(string $identifier, string $instance, CartRevision $expectedRevision): bool;

    // Non-revisioned reads
    public function getItems(string $identifier, string $instance): array;
    public function getConditions(string $identifier, string $instance): array;

    // Metadata
    public function getMetadata(string $identifier, string $instance, string $key): mixed;
    public function getAllMetadata(string $identifier, string $instance): array;
    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void;
    public function mutateMetadata(string $identifier, string $instance, string $key, Closure $compute): mixed;
    public function putMetadataBatch(string $identifier, string $instance, array $metadata): void;
    public function clearMetadata(string $identifier, string $instance): void;
    public function clearAll(string $identifier, string $instance): void;

    // Versioning
    public function getVersion(string $identifier, string $instance): ?int;
    public function getId(string $identifier, string $instance): ?string;
    public function getCreatedAt(string $identifier, string $instance): ?string;
    public function getUpdatedAt(string $identifier, string $instance): ?string;
    public function getExpiresAt(string $identifier, string $instance): ?string;
    public function isExpired(string $identifier, string $instance): bool;

    // Migration
    public function swapIdentifier(string $oldId, string $newId, string $instance): bool;
    public function getInstances(string $identifier): array;
    public function forgetIdentifier(string $identifier): void;
}
```

### The Revision Contract

Every read/modify/write cycle must be based on a single coherent read:
`getSnapshot()` returns the cart content together with the exact `CartRevision`
(row UUID + version) it was read at. The replacing write
(`putItems()`, `putConditions()`, `putBoth()`) requires that revision and is
rejected with `CartConflictException` when a concurrent writer moved it.

```php
$snapshot = $storage->getSnapshot($identifier, $instance);

// Compute the replacement from $snapshot->items ...

// Commits only if the row is still exactly the one that was read.
$revision = $storage->putItems(
    $identifier,
    $instance,
    $replacement,
    $snapshot?->revision() ?? CartRevision::none(),
);
```

`CartRevision::none()` means "no row expected": the write creates a new cart
and conflicts when one already exists for the `(owner_scope, identifier,
instance)` identity. Because the expectation pairs the row UUID with the
version, a snapshot taken before a cart was deleted and recreated can never
overwrite the replacement row.

Conflicts are never retried with the stale payload. Domain operations
(`Cart::add()`, `update()`, metadata writes, guest merges) re-run the whole
mutation against a fresh snapshot a bounded number of times before surfacing
`CartConflictException`. Result data is attempt-local: a losing attempt
dispatches no events and never decides the return value of the committed one.

Automatic empty-cart cleanup (`destroy`/`clear` when the last item is removed)
is part of the same consistency boundary: `forgetIfUnchanged()` and
`clearAllIfUnchanged()` only apply while the revision the emptying mutation
committed is still current. An item, condition, or metadata value written by an
event listener or a concurrent request between the commit and the cleanup is
never erased, and no cleanup event is dispatched when the guard rejected.

Operations that touch two carts — identifier swaps and guest-to-user merges —
always access the source cart before the target, and write the target before
tombstoning the source. Keep that order for new two-cart operations so a
future switch to row locks cannot deadlock between the two paths.

## Database Storage

The default production storage using database persistence.

### Features

- **Revision-checked writes** - Compare-and-swap over the full read snapshot prevents lost updates
- **Atomic creation** - The `(owner_scope, identifier, instance)` unique identity makes concurrent first creation safe
- **TTL Support** - Automatic cart expiration
- **Owner Scoping** - Multi-tenant data isolation
- **JSON Columns** - Efficient storage with optional JSONB indexes

### Configuration

```php
// config/cart.php
'database' => [
    'table' => 'carts',
    'ttl' => 60 * 60 * 24 * 30, // 30 days
    'lock_for_update' => false, // Add SELECT ... FOR UPDATE to read-then-write helpers
],
```

### Revision-Checked Writes

Every cart has a `version` column. Replacing writes are guarded by the exact
revision read from `getSnapshot()`:

```php
// Pseudo-code of the version-checked update
UPDATE carts SET ..., version = version + 1
    WHERE id = $revision->cartId AND version = $revision->version;

// 0 rows updated -> CartConflictException (the concurrent write is preserved)
```

A write with `CartRevision::none()` takes the creation path instead:

```php
INSERT INTO carts (...owner identity..., version = 1);

// Unique violation on (owner_scope, identifier, instance)
// -> CartConflictException (a cart was created concurrently)
```

The `carts` table carries a composite `(identifier, instance, version)` index
so version-checked lookups never scan.

### Metadata and Clears

Metadata writes are storage-owned read/modify/write merges: `putMetadata()`
(and `putMetadataBatch()`) recompute against fresh stored metadata, so
concurrent writers of different keys never clobber each other. No-op merges
skip the write entirely and leave the version untouched. `mutateMetadata()`
computes the new value from the fresh current value inside the storage.

`clearMetadata()` and `clearAll()` are blind resets keyed only by
`(owner_scope, identifier, instance)`; they bump the version on retry so a
concurrent write cannot be silently overwritten without detection. Explicit
`Cart::destroy()` and `Cart::clear()` keep using these blind resets.

Automatic empty-cart cleanup uses the guarded variants instead:
`forgetIfUnchanged()` and `clearAllIfUnchanged()` apply only while the revision
the emptying mutation committed is still current, so a newer write is never
erased. A rejected guard is silent: no cleanup event is dispatched.

### Handling Conflicts

Domain operations retry against fresh content internally; the exception only
surfaces after those bounded retries are exhausted under sustained contention.
Retrying the domain call itself is safe: it recomputes from a fresh snapshot.

```php
use AIArmada\Cart\Exceptions\CartConflictException;

try {
    Cart::add('SKU-001', 'Product', 999, 1);
} catch (CartConflictException $e) {
    // A competing write kept winning the revision race
    $e->getAttemptedVersion(); // Revision this write expected (0 = creation attempt)
    $e->getCurrentVersion();   // Revision actually in storage (0 = row gone)
    $e->getVersionDifference();
    $e->isMinorConflict();
    $e->getResolutionSuggestions();

    // Retry the domain call (it re-reads) or inform the user
}
```

### PostgreSQL JSONB Indexes

The migration automatically creates GIN indexes for JSONB columns:

```sql
CREATE INDEX carts_items_gin_index ON carts USING GIN (items);
CREATE INDEX carts_conditions_gin_index ON carts USING GIN (conditions);
CREATE INDEX carts_metadata_gin_index ON carts USING GIN (metadata);
```

## Owner Scoping

Database storage supports multi-tenant isolation:

```php
// Get storage with owner scope
$storage = Cart::storage()->withOwner($tenant);

// All operations now scoped to tenant
$storage->getItems('user-123', 'default'); // Only tenant's carts
```

### How It Works

**Database Storage:**
```sql
SELECT * FROM carts 
WHERE identifier = ? 
  AND instance = ?
  AND owner_type = 'App\Models\Tenant'
  AND owner_id = '123'
```

## Data Validation

Storage validates data before persistence:

### Size Limits

```php
'limits' => [
    'max_items' => 1000,
    'max_data_size_bytes' => 1048576, // 1MB
],
```

### Serialization Validation

Non-serializable values are rejected:

```php
// These will throw InvalidArgumentException:
Cart::setMetadata('callback', fn() => 'test'); // Closures
Cart::setMetadata('resource', fopen('file', 'r')); // Resources
Cart::setMetadata('object', new NonSerializableClass()); // Non-JSON objects
```

## Cart Migration

> **info**
> Guest-to-user migration is atomic in the actual storage backend. The two-cart
> boundary lives in `StorageInterface::commitMergeTransfer()`, which validates
> both source and target revisions and commits target data plus source
> consumption as one unit. `DatabaseStorage` runs this inside a transaction on
> the connection that owns the storage writes (not the default facade), and
> `InMemoryStorage` stages both sides and reverts only its own target write
> when the source consumption loses, preserving a competitor's successful
> mutation. Decorators and custom backends must implement
> `commitMergeTransfer()` and `backingKey()`; incompatible backing stores are
> rejected before any mutation.

Transfer carts between identifiers (e.g., guest to user):

```php
use AIArmada\Cart\Services\CartMigrationService;

$migrationService = app(CartMigrationService::class);

// Swap cart ownership
$migrationService->swapGuestCartToUser(
    userId: 123,
    instance: 'default',
    guestSessionId: 'session-abc'
);

// Full migration with merge
$result = $migrationService->migrateGuestCartForUser(
    user: $user,
    instance: 'default',
    sessionId: $guestSessionId
);

// $result->itemsMerged is the sum of guest quantities migrated.
```

User keys use the repository's scalar key contract: integer IDs and UUID
strings are both accepted and preserved byte-for-byte. A UUID user key is
never truncated to its numeric prefix, an authenticated user without a usable
ID resolves through the same shared resolver as the cart manager instead of
falling back to a guest session, and an empty or missing user key fails
clearly (`InvalidArgumentException` from `swapGuestCartToUser()` /
`execute()`, a failure result from `executeForUser()`) instead of migrating
into an unrelated identity.

> **warning**
> `swap()` transfers a cart only when the target identifier is empty. A swap
> into an occupied target is refused (`false`, both carts untouched) instead of
> overwriting the target. Use the guest-to-user migration when contents must be
> merged rather than transferred.

The service API is available for integrations that need identifier management. The dedicated Action classes below are the preferred orchestration path for application workflows.

itemsMerged reports the sum of quantities from the guest instance captured
before migration. It is 0 when migration fails or no guest items exist.

### Action-Based Migration

```php
use AIArmada\Cart\Actions\MigrateGuestCartToUserAction;

$action = app(MigrateGuestCartToUserAction::class);

// Execute raw migration (returns bool)
$action->execute(
    userId: 123,
    instance: 'default',
    sessionId: 'session-abc'
);

// With custom merge strategy
$action = $action->withMergeStrategy($customStrategy);
$action->execute(userId: 123, instance: 'default', sessionId: 'session-abc');

// Convenience wrapper for user models
$result = $action->executeForUser(
    user: $user,
    instance: 'default',
    sessionId: $guestSessionId
);
// $result->success, $result->itemsMerged, $result->message
```

```php
use AIArmada\Cart\Actions\MigrateCartOnLoginAction;

$action = app(MigrateCartOnLoginAction::class);

// The guest session id is required. Omit it and the action short-circuits
// with success = false; `HandleUserLogin` is what pulls the id off the
// session stashed by `HandleUserLoginAttempt`.
$result = $action->execute(user: $user, instance: 'default', sessionId: 'session-abc');

// Returns ['success' => bool, 'itemsMerged' => int, 'message' => string]
$result['success'];
```

### Auto-Migration on Login

When enabled, carts migrate automatically:

```php
// config/cart.php
'migration' => [
    'auto_migrate_on_login' => true,
    'merge_strategy' => 'add_quantities',
],
```

The package listens to `Attempting` and `Login` events:

1. `HandleUserLoginAttempt` - Captures session ID before auth regenerates it
2. `HandleUserLogin` - Migrates cart using captured session ID

HandleUserLogin calls StorageInterface::getInstances($sessionId) and migrates
each discovered instance. If none are returned it attempts default. Owner-aware
storage is switched to explicit global storage while discovering guest
instances, then each migration writes to the authenticated owner scope. The
listener resolves storage when handling the login so owner-sensitive storage
is not constructed while the listener itself is being resolved.

## Custom Storage

Implement `StorageInterface` for custom backends:

```php
class RedisStorage implements StorageInterface
{
    public function getItems(string $identifier, string $instance): array
    {
        $key = "cart:{$identifier}:{$instance}:items";
        return json_decode(Redis::get($key), true) ?? [];
    }
    
    // ... implement all interface methods
}

// Register
$this->app->bind('cart.storage', RedisStorage::class);
```

> **danger**
> Custom storage must enforce the same revision contract as `DatabaseStorage`,
> including `backingKey()` and `commitMergeTransfer()` for atomic
> guest-to-user transfers:
> `getSnapshot()` must return content and revision from one coherent read, and
> every replacing write must reject a stale `CartRevision` with
> `CartConflictException` instead of overwriting. `CartRevision::none()` must
> only ever create, never overwrite. `mutateMetadata()` must compute from the
> fresh stored value. `forgetIfUnchanged()` and `clearAllIfUnchanged()` must
> apply only when the row UUID and version still match the expected revision,
> and must reject a creation revision, so automatic empty-cart cleanup can
> never erase a newer write. The shipped `InMemoryStorage` enforces this
> contract and is covered by the same storage contract tests, so it is the
> reference implementation for test doubles.
