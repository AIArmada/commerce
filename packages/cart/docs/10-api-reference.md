---
title: API Reference
---

# API Reference

Complete API documentation for the Cart package.

## Cart Facade

The `Cart` facade provides static access to all cart functionality.

### Basic Operations

```php
use AIArmada\Cart\Facades\Cart;

// Instance management
Cart::setInstance('wishlist');       // Switch to named instance
Cart::instance();                    // Get current instance name

// Item operations
Cart::add(
    id: 'SKU-001',
    name: 'Product Name',
    price: 1999,                     // Price in cents
    quantity: 1,
    attributes: ['size' => 'M'],
    options: ['model_type' => Product::class, 'model_id' => '...']
);
Cart::update('SKU-001', ['quantity' => 2]);
Cart::remove('SKU-001');
Cart::get('SKU-001');                // Get single item
Cart::has('SKU-001');                // Check if item exists
Cart::content();                     // Get all items
Cart::countItems();                  // Count unique items
Cart::getTotalQuantity();            // Sum of all quantities
Cart::clear();                       // Remove all items
Cart::destroy();                     // Destroy cart completely

// Totals
Cart::subtotal();                    // Subtotal as Money object
Cart::getRawSubtotal();              // Subtotal in cents
Cart::total();                       // Total as Money object
Cart::getRawTotal();                 // Total in cents
```

### Condition Operations

```php
// Cart-level conditions
Cart::addCondition($condition);
Cart::getCondition('condition-name');
Cart::getConditions();
Cart::removeCondition('condition-name');
Cart::clearConditions();

// Type-specific helpers
Cart::addShipping($name, $value);
Cart::addTax($name, $value);
Cart::addDiscount($name, $value);

// Item-level conditions
Cart::addItemCondition('SKU-001', $condition);
Cart::get('SKU-001')->getConditions();
Cart::removeItemCondition('SKU-001', 'condition-name');
Cart::clearItemConditions('SKU-001');
```

### Dynamic Conditions

```php
// Registration
Cart::registerDynamicCondition($condition, callable $rule, $metadata);
Cart::registerDynamicCondition($condition, 'subtotal-at-least', ['amount' => 5000]);

// Management
Cart::getDynamicConditions();
Cart::removeDynamicCondition('condition-name');
Cart::clearDynamicConditions();

// Evaluation
Cart::evaluateDynamicConditions();
Cart::isDynamicConditionsDirty();
Cart::markDynamicConditionsDirty();

// Factory
Cart::setRulesFactory($factory);
Cart::getRulesFactory();
```

### Metadata Operations

```php
Cart::setMetadata('key', 'value');
Cart::setMetadataBatch(['key1' => 'v1', 'key2' => 'v2']);
Cart::getMetadata('key');
Cart::getMetadata('key', 'default');
Cart::getAllMetadata();
Cart::hasMetadata('key');
Cart::removeMetadata('key');
Cart::clearMetadata();
```

### Multi-tenancy

```php
Cart::forOwner($owner);              // Set owner context
```

### Pipeline Control

```php
Cart::invalidatePipelineCache();
Cart::withLazyPipeline();
Cart::withoutLazyPipeline();
Cart::isLazyPipelineEnabled();
Cart::getPipelineCacheStats();
```

### Identifiers

```php
Cart::getId();                       // UUID or null
Cart::getIdentifier();               // Session/user ID
Cart::getVersion();                  // Optimistic lock version
```

---

## CartCondition

The core condition class for price modifications.

### Constructor

```php
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Target;

$condition = new CartCondition(
    name: 'discount-10-percent',
    type: 'discount',
    target: Target::cart()->build(),
    value: '-10%',
    attributes: [
        'description' => '10% Off',
        'priority' => 100,
    ]
);
```

### Value Formats

```php
// Fixed amount (cents)
$condition->setValue(500);           // +$5.00
$condition->setValue(-500);          // -$5.00

// Percentage
$condition->setValue('10%');         // +10%
$condition->setValue('-10%');        // -10%
```

### Methods

```php
// Getters
$condition->getName();
$condition->getType();
$condition->getValue();
$condition->getTargetDefinition();
$condition->getAttributes();
$condition->getAttribute('key', 'default');

// Calculated values
$condition->getCalculatedValue(10000);      // Apply to base value
$condition->apply(10000);                   // Same as above

// Type checks
$condition->isPercentage();
$condition->isDiscount();
$condition->isCharge();

// Targeting
$condition->getTargetDefinition();           // ConditionTarget value object
$condition->getTargetDefinition()->scope;    // ConditionScope enum
$condition->getTargetDefinition()->phase;    // ConditionPhase enum

// Serialization
$condition->toArray();
CartCondition::fromArray($array);
```

---

## CartItem

Read-only item representation.

### Properties

```php
$item->id;                           // Item ID (SKU)
$item->name;                         // Item name
$item->price;                        // Price in cents (int)
$item->quantity;                     // Quantity (int)
$item->attributes;                   // Custom attributes (array)
$item->associatedModel;              // Related Eloquent model or null
```

### Methods

```php
// Totals
$item->subtotal();                   // Money object
$item->getRawSubtotal();             // Cents
$item->total();                      // With conditions, Money
$item->getRawSubtotalWithoutConditions(); // Without conditions, cents

// Conditions
$item->getConditions();
$item->hasConditions();

// Model association
$item->getAssociatedModel();
```

---

## Collections

### CartCollection

Collection of CartItem objects.

```php
$collection = Cart::content();

// Methods
$collection->getTotalQuantity();     // Sum of quantities
$collection->subtotal();             // Sum of subtotals
$collection->getItem('SKU-001');     // Find specific item
$collection->toArray();              // Serialize for storage
```

### CartConditionCollection

Collection of CartCondition objects.

```php
$conditions = Cart::getConditions();

// Filter by type
$conditions->byType('discount');
$conditions->discounts();
$conditions->charges();
$conditions->percentages();

// Get specific
$conditions->getCondition('condition-name');

// Sort
$conditions->sortByOrder();

// Totals
$conditions->applyAll(10000);  // Apply all to base
```

---

## Storage Interface

### StorageInterface Methods

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

    // Items
    public function getItems(string $identifier, string $instance): array;
    public function putItems(string $identifier, string $instance, array $items): void;

    // Conditions
    public function getConditions(string $identifier, string $instance): array;
    public function putConditions(string $identifier, string $instance, array $conditions): void;

    // Combined
    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void;

    // Metadata
    public function getMetadata(string $identifier, string $instance, string $key): mixed;
    public function getAllMetadata(string $identifier, string $instance): array;
    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void;
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

---

## Events

All events implement `CartEventInterface` with these methods:

```php
$event->getEventType();              // e.g., 'cart.item.added'
$event->getCartIdentifier();         // Cart identifier
$event->getCartInstance();           // Instance name
$event->getCartId();                 // UUID or null
$event->getEventId();                // Unique event UUID
$event->getOccurredAt();             // DateTimeImmutable
$event->toArray();                   // Serializable data
```

### Event Classes

| Event | Data |
|-------|------|
| `ItemAdded` | `$item`, `$cart` |
| `ItemUpdated` | `$item`, `$cart` |
| `ItemRemoved` | `$item`, `$cart` |
| `CartConditionAdded` | `$condition`, `$cart` |
| `CartConditionRemoved` | `$condition`, `$cart`, `$reason` |
| `ItemConditionAdded` | `$condition`, `$cart`, `$itemId` |
| `ItemConditionRemoved` | `$condition`, `$cart`, `$itemId`, `$reason` |
| `MetadataAdded` | `$key`, `$value`, `$cart` |
| `MetadataBatchAdded` | `$metadata`, `$cart` |
| `MetadataRemoved` | `$key`, `$cart` |
| `MetadataCleared` | `$cart` |
| `CartCleared` | `$cart` |
| `CartCreated` | `$cart` |
| `CartDestroyed` | `$identifier`, `$instance`, `$cartId` |
| `CartMerged` | `$targetCart`, `$sourceCart`, `$totalItemsMerged`, `$mergeStrategy`, `$hadConflicts` |

---

## Exceptions

### CartException

Base exception class.

### CartConflictException

Thrown on optimistic lock conflicts.

```php
$e->getAttemptedVersion();           // Version client had
$e->getCurrentVersion();             // Actual version in storage
$e->getVersionDifference();          // How many versions behind
$e->isMinorConflict();               // True if 1 version behind
$e->getResolutionSuggestions();      // Array of suggestions
$e->getConflictedCart();             // Cart if available
$e->getConflictedData();             // Data if available
$e->toArray();                       // API-friendly format
```

### InvalidCartItemException

Thrown when item validation fails.

### InvalidCartConditionException

Thrown when condition validation fails.

### ProductNotPurchasableException

Thrown when product can't be added.

```php
$e->productId;
$e->productName;
$e->reason;
$e->requestedQuantity;
$e->availableStock;

// Static factories
ProductNotPurchasableException::outOfStock($id, $name, $requested, $available);
ProductNotPurchasableException::inactive($id, $name);
ProductNotPurchasableException::minimumNotMet($id, $name, $requested, $min);
ProductNotPurchasableException::maximumExceeded($id, $name, $requested, $max);
ProductNotPurchasableException::invalidIncrement($id, $name, $requested, $increment);
```

### UnknownModelException

Thrown when associated model class is unknown.

---

## Enums

Condition types are plain strings (`discount`, `tax`, `shipping`, `fee`, ...). Targets use the `ConditionTarget` value object (`AIArmada\Cart\Conditions\ConditionTarget`) built via `Target::cart()` / `Target::items()`.

### ConditionScope

```php
use AIArmada\Cart\Conditions\Enums\ConditionScope;

enum ConditionScope: string
{
    case CART = 'cart';
    case ITEMS = 'items';
    case CUSTOM = 'custom';
}
```

### ConditionPhase

```php
use AIArmada\Cart\Conditions\Enums\ConditionPhase;

enum ConditionPhase: string
{
    case PRE_ITEM = 'pre_item';
    case ITEM_DISCOUNT = 'item_discount';
    case ITEM_POST = 'item_post';
    case CART_SUBTOTAL = 'cart_subtotal';
    case SHIPPING = 'shipping';
    case TAXABLE = 'taxable';
    case TAX = 'tax';
    case PAYMENT = 'payment';
    case GRAND_TOTAL = 'grand_total';
    case CUSTOM = 'custom';
}
```

### ConditionApplication

```php
use AIArmada\Cart\Conditions\Enums\ConditionApplication;

enum ConditionApplication: string
{
    case AGGREGATE = 'aggregate';
    case PER_ITEM = 'per-item';
    case PER_UNIT = 'per-unit';
    case PER_GROUP = 'per-group';
}
```

### ConditionFilterOperator

```php
use AIArmada\Cart\Conditions\Enums\ConditionFilterOperator;

enum ConditionFilterOperator: string
{
    case EQ = '=';
    case NEQ = '!=';
    case GT = '>';
    case GTE = '>=';
    case LT = '<';
    case LTE = '<=';
    case IN = 'in';
    case NOT_IN = 'not-in';
    case CONTAINS = '~';
    case NOT_CONTAINS = '!~';
    case STARTS_WITH = 'starts_with';
    case ENDS_WITH = 'ends_with';
}
```

### CartMergeStrategy

```php
enum CartMergeStrategy: string
{
    case ADD_QUANTITIES = 'add_quantities';
    case KEEP_HIGHEST_QUANTITY = 'keep_highest_quantity';
    case KEEP_USER_CART = 'keep_user_cart';
    case REPLACE_WITH_GUEST = 'replace_with_guest';
}
```

---

## Artisan Commands

### cart:clear-abandoned

Clear abandoned shopping carts.

```bash
# Clear carts older than 7 days (default)
php artisan cart:clear-abandoned

# Custom days
php artisan cart:clear-abandoned --days=30

# Only clear expired carts (using expires_at)
php artisan cart:clear-abandoned --expired

# Dry run (preview what would be deleted)
php artisan cart:clear-abandoned --dry-run

# Custom batch size
php artisan cart:clear-abandoned --batch-size=500
```

---

## Actions

### MigrateGuestCartToUserAction

```php
use AIArmada\Cart\Actions\MigrateGuestCartToUserAction;

$action = app(MigrateGuestCartToUserAction::class);

// Execute raw migration (returns bool)
$action->execute(userId: 123, instance: 'default', sessionId: 'session-abc');

// With custom merge strategy
$action = $action->withMergeStrategy($customStrategy);
$action->execute(userId: 123, instance: 'default', sessionId: 'session-abc');

// With user model
$result = $action->executeForUser(
    user: $user,
    instance: 'default',
    sessionId: $guestSessionId
);
$result->success;
$result->itemsMerged;
$result->message;
```

### MigrateCartOnLoginAction

```php
use AIArmada\Cart\Actions\MigrateCartOnLoginAction;

$action = app(MigrateCartOnLoginAction::class);

$result = $action->execute(user: $user, instance: 'default');
// $result['success'], $result['itemsMerged'], $result['message']

$result = $action->execute(user: $user, instance: 'default', sessionId: 'session-abc');
```

---

## Services

### CartMigrationService

Handles guest-to-user cart migration.

```php
use AIArmada\Cart\Services\CartMigrationService;

$service = app(CartMigrationService::class);

// Migrate guest cart to user
$result = $service->migrateGuestCartForUser($user, 'default', $oldSessionId);

// Access result
$result->success;          // bool
$result->itemsMerged;      // int
$result->conflicts;        // array
$result->message;          // string
```

### BuiltInRulesFactory

Creates rule callables from factory keys.

```php
use AIArmada\Cart\Services\BuiltInRulesFactory;

$factory = app(BuiltInRulesFactory::class);

// Get rule from key
$rule = $factory->createRules('subtotal-at-least', ['amount' => 5000]);

// Check if key is supported
$factory->canCreateRules('subtotal-at-least'); // true
```

### RulePresets

Static factory methods for common rules.

```php
use AIArmada\Cart\Services\RulePresets;

// Value rules
$rule = RulePresets::minimumCartValue(5000);
$rule = RulePresets::cartValueBetween(1000, 10000);
$rule = RulePresets::maximumCartValue(50000);

// Quantity rules
$rule = RulePresets::minimumQuantity(3);
$rule = RulePresets::minimumItems(2);

// Product rules
$rule = RulePresets::requireAnyProduct(['SKU-1', 'SKU-2']);
$rule = RulePresets::requireAllProducts(['SKU-1', 'SKU-2']);
$rule = RulePresets::requireProduct('SKU-1');

// Time rules
$rule = RulePresets::requireWeekday();
$rule = RulePresets::requireWeekend();
$rule = RulePresets::timeWindow('09:00', '17:00');
$rule = RulePresets::dateRange('2024-01-01', '2024-01-31');

// Customer rules
$rule = RulePresets::requireAuthenticated();
$rule = RulePresets::requireCustomerTag('premium');
$rule = RulePresets::requireVip();

// Metadata rules
$rule = RulePresets::requireMetadata('coupon_code');
$rule = RulePresets::requireMetadataValue('channel', 'mobile');

// Combinators
$rule = RulePresets::all($rule1, $rule2);    // AND
$rule = RulePresets::any($rule1, $rule2);    // OR
$rule = RulePresets::not($rule);             // NOT
```

### CartMergeStrategyRegistry

Registers and resolves merge strategies by name.

```php
use AIArmada\Cart\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Enums\CartMergeStrategy;

$registry = app(CartMergeStrategyRegistry::class);

// Register built-in strategies
$registry->registerBuiltIns();

// Resolve from config
$strategy = $registry->resolveFromConfig();

// Get by name
$strategy = $registry->get(CartMergeStrategy::ADD_QUANTITIES->value);

// Register custom strategy
$registry->register($yourHandler, 'my-strategy');
```

### CartFactory

Creates `Cart` instances with shared dependencies.

```php
use AIArmada\Cart\Services\CartFactory;

$factory = app(CartFactory::class);

$cart = $factory->make(identifier: 'user-123', instanceName: 'default');
$clone = $factory->cloneForIdentifier($cart, 'user-456');
$wishlist = $factory->cloneForInstance($cart, 'wishlist');
```

### ConditionPipelineFactory

Configures and creates condition pipeline instances.

```php
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelineFactory;

$factory = app(ConditionPipelineFactory::class);

$pipeline = $factory->createEager();
$lazy = $factory->createLazy($context);

$factory->configure(function ($pipeline) {
    // Register pipeline stages
});
```

### ConditionTypeHandlerRegistry

Registers handlers for condition types (tax, shipping, discount, fee).

```php
use AIArmada\Cart\Conditions\Handlers\ConditionTypeHandlerRegistry;

$registry = app(ConditionTypeHandlerRegistry::class);

$registry->register($handler);
$handler = $registry->get('tax');
$allHandlers = $registry->all();
```

---

## Contracts

### CartMergeStrategyInterface

```php
use AIArmada\Cart\Contracts\CartMergeStrategyInterface;

interface CartMergeStrategyInterface
{
    public function resolveConflict(int $userQuantity, int $guestQuantity): int;
}
```

---

## Support

### Login Migration Stash

Login-based migration keys off the guest's own session: the login-attempt
listener stashes the pre-login session id under
`HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY`, and the login listener pulls it
from that same session. Migration state is never keyed by a login identifier,
so one session cannot plant a migration into another account's login.

```php
use AIArmada\Cart\Listeners\HandleUserLoginAttempt;

session()->put(HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY, session()->getId());
$guestSessionId = session()->pull(HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY);
```
