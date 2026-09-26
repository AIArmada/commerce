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
Cart::setInstance('wishlist');        // Switch the current named instance
Cart::instance();                    // Get current instance name (string)

// Item operations
Cart::add(
    id: 'SKU-001',
    name: 'Product Name',
    price: 1999,                     // Price in minor units
    quantity: 1,
    attributes: ['size' => 'M'],
    associatedModel: $product        // Eloquent model instance or class-string
);
Cart::update('SKU-001', ['quantity' => 2]);
Cart::remove('SKU-001');
Cart::get('SKU-001');                // Get single item
Cart::has('SKU-001');                // Check if item exists
Cart::getItems();                    // CartCollection of CartItem
Cart::getContent();                  // Full cart snapshot as array
Cart::countItems();                  // Count unique items
Cart::getTotalQuantity();            // Sum of all quantities
Cart::clear();                       // Remove all items
Cart::destroy();                     // Destroy cart completely

// Totals
Cart::subtotal();                    // Subtotal as Money object
Cart::getRawSubtotal();              // Subtotal in minor units
Cart::total();                       // Total as Money object
Cart::getRawTotal();                 // Total in minor units
```

### Condition Operations

```php
// Cart-level conditions
Cart::addCondition($condition);      // CartCondition or condition array
Cart::getCondition('condition-name');
Cart::getConditions();
Cart::getConditionsByType('discount');
Cart::removeCondition('condition-name');
Cart::removeConditionsByType('discount');
Cart::clearConditions();

// Convenience factories (target is ConditionTarget|DSL string|array|null)
Cart::addDiscount(string $name, string $value, $target = null);
Cart::addTax(string $name, string $value, $target = null);
Cart::addFee(string $name, string $value, $target = null);
Cart::addShipping(string $name, string|int|float $value, $target = null, string $method = 'standard', array $attributes = []);
Cart::removeShipping();
Cart::getShipping();
Cart::getShippingMethod();
Cart::getShippingValue();

// Item-level conditions
Cart::addItemCondition('SKU-001', $condition);
Cart::removeItemCondition('SKU-001', 'condition-name');
Cart::clearItemConditions('SKU-001');
```

### Dynamic Conditions

```php
// Registration
Cart::registerDynamicCondition(condition: $condition, rules: RulePresets::minimumCartValue(5000));
Cart::registerDynamicCondition(condition: $condition, ruleFactoryKey: 'subtotal-at-least', metadata: ['amount' => 5000]);

// Management
Cart::getDynamicConditions();
Cart::removeDynamicCondition('condition-name');
Cart::clearDynamicConditions();

// Evaluation
Cart::evaluateDynamicConditions();
Cart::isDynamicConditionsDirty();
Cart::markDynamicConditionsDirty();
Cart::evaluateDynamicConditionsIfDirty();
Cart::restoreDynamicConditions();
Cart::getDynamicConditionMetadata();
Cart::onDynamicConditionFailure(fn ($operation, $condition, $exception, $context) => report($exception));

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
Cart::forOwner($owner);              // CartManager::forOwner(Model $owner) — non-nullable
```

There is no `Cart::forOwner(null)`. For explicit global work use
`OwnerContext::withOwner(null, fn () => ...)` or `Cart::storage()->withOwner(null)`.

### Pipeline Control

```php
Cart::invalidatePipelineCache();
Cart::withLazyPipeline();
Cart::withoutLazyPipeline();
Cart::isLazyPipelineEnabled();
Cart::getPipelineCacheStats();
Cart::evaluateConditionPipeline();   // ConditionPipelineResult (evaluatePipelineWithCaching() is protected)
```

### Identifiers

```php
Cart::getId();                       // UUID or null
Cart::getIdentifier();               // Session/user ID
Cart::getVersion();                  // Optimistic lock version
```

---

## CartCondition

The core condition class for price modifications. It is immutable; use
`with()` to derive a modified copy.

### Constructor

```php
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Target;

$condition = new CartCondition(
    name: 'discount-10-percent',
    type: 'discount',
    target: Target::cart()->phase(ConditionPhase::CART_SUBTOTAL)->build(),
    value: '-10%',
    attributes: [
        'description' => '10% Off',
        'priority' => 100,
    ],
    order: 10
);
```

### Value Formats

Values are set through the constructor or `with()`. There is no `setValue()`.

```php
// Fixed amount (minor units)
new CartCondition(name: 'x', type: 'fee', target: $target, value: 500);
new CartCondition(name: 'x', type: 'discount', target: $target, value: -500);

// Percentage
new CartCondition(name: 'x', type: 'fee', target: $target, value: '+10%');
new CartCondition(name: 'x', type: 'discount', target: $target, value: '-10%');
```

### Methods

```php
// Getters
$condition->getName();
$condition->getType();
$condition->getValue();
$condition->getTargetDefinition();
$condition->getOrder();
$condition->getAttributes();
$condition->getAttribute('key', 'default');
$condition->hasAttribute('key');

// Calculated values
$condition->getCalculatedValue(10000);      // Adjustment relative to base
$condition->apply(10000);                   // Resulting amount

// Type checks
$condition->isPercentage();
$condition->isDiscount();
$condition->isCharge();
$condition->isDynamic();
$condition->getRules();
$condition->shouldApply($cart, $item);      // Evaluate rule closures
$condition->getPercentageRate();            // PercentageRate (basis points)

// Derivation
$condition->with(['value' => '-20%']);
$condition->withoutRules();                 // Static copy for direct application

// Serialization
$condition->toArray();
$condition->toJson();
CartCondition::fromArray($data);
```

---

## CartItem

Read-only item representation.

### Properties

```php
$item->id;                           // Item ID (SKU), string
$item->name;                         // Item name
$item->price;                        // Unit price in minor units (int)
$item->quantity;                     // Quantity (int)
$item->conditions;                   // CartConditionCollection
$item->attributes;                   // Illuminate\Support\Collection
$item->associatedModel;              // Related Eloquent model or class-string or null
```

### Methods

```php
// Totals
$item->subtotal();                   // Money, price × quantity incl. conditions
$item->getRawSubtotal();             // Same in minor units
$item->getSubtotal();                // Money
$item->getSubtotalWithoutConditions();
$item->total();                      // Money, alias of subtotal()
$item->getPrice();                   // Money
$item->getRawPrice();                // Minor units
$item->discountAmount();             // Money

// Conditions
$item->getConditions();
$item->hasConditions();
$item->getCondition('name');

// Model association
$item->getAssociatedModel();
$item->isAssociatedWith(Product::class);
```

---

## Collections

### CartCollection

Collection of CartItem objects.

```php
$collection = Cart::getItems();

// Methods
$collection->getTotalQuantity();     // Sum of quantities
$collection->subtotal();             // Sum of subtotals (Money)
$collection->total();                // Sum of totals (Money)
$collection->getItem('SKU-001');     // Find specific item
$collection->hasItem('SKU-001');
$collection->getTotalDiscount();
$collection->filterByAttribute('size', 'M');
$collection->toArray();              // Serialize for storage
```

`Cart::content()` and `Cart::getContent()` are the full cart snapshot arrays
(items, conditions, totals, metadata, timestamps), not item collections.

### CartConditionCollection

Collection of CartCondition objects.

```php
$conditions = Cart::getConditions();

// Filter by type / target / phase
$conditions->byType('discount');
$conditions->byTarget('cart@cart_subtotal/aggregate');
$conditions->byPhase(ConditionPhase::CART_SUBTOTAL);
$conditions->discounts();
$conditions->charges();
$conditions->percentages();

// Get specific
$conditions->getCondition('condition-name');
$conditions->hasCondition('condition-name');

// Sort
$conditions->sortByOrder();

// Totals
$conditions->applyAll(10000);              // Money, all conditions applied to base
$conditions->getTotalDiscount(10000);      // Minor units
$conditions->getTotalCharges(10000);       // Minor units
$conditions->getSummary(10000);
```

---

## Storage Interface

### StorageInterface Methods

The full contract is documented in [08-storage.md](08-storage.md). Signatures that
are most commonly used:

```php
use AIArmada\Cart\Storage\StorageInterface;

interface StorageInterface
{
    // Owner scoping
    public function withOwner(?Model $owner): static;
    public function getOwnerType(): ?string;
    public function getOwnerId(): string|int|null;

    // Items CRUD
    public function getItems(string $identifier, string $instance): array;
    public function putItems(string $identifier, string $instance, array $items): void;

    // Conditions CRUD
    public function getConditions(string $identifier, string $instance): array;
    public function putConditions(string $identifier, string $instance, array $conditions): void;
    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void;

    // Metadata CRUD
    public function getAllMetadata(string $identifier, string $instance): array;
    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void;
    public function getMetadata(string $identifier, string $instance, string $key): mixed;
    public function clearMetadata(string $identifier, string $instance): void;

    // Existence and lifecycle
    public function has(string $identifier, string $instance): bool;
    public function getId(string $identifier, string $instance): ?string;
    public function getVersion(string $identifier, string $instance): ?int;
    public function isExpired(string $identifier, string $instance): bool;
    public function clearAll(string $identifier, string $instance): void;
    public function forget(string $identifier, string $instance): void;
    public function forgetIdentifier(string $identifier): void;
    public function flush(): void;

    // Migration
    public function swapIdentifier(string $oldId, string $newId, string $instance): bool;
    public function getInstances(string $identifier): array;
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
$event->getOccurredAt();              // DateTimeImmutable
$event->toEventPayload();             // Serializable data
$event->getEventMetadata();           // Request context (event_id, occurred_at, user_agent, ip_address, correlation_id)
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

There is no `ConditionType` enum. A condition's `type` is a free-form string
(`discount`, `tax`, `fee`, `shipping`, `shipping_discount`, `item_discount`,
`surcharge`, `tax_exemption`, ...) that handlers and `getConditionsByType()`
match on. The real condition enums live in `AIArmada\Cart\Conditions\Enums`.

### ConditionScope

```php
enum ConditionScope: string
{
    case CART = 'cart';
    case ITEMS = 'items';
    case CUSTOM = 'custom';
}
```

### ConditionPhase

```php
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

### EmptyCartBehavior

```php
enum EmptyCartBehavior: string
{
    case Destroy = 'destroy';   // Remove cart row entirely
    case Clear = 'clear';       // Keep row, clear items/conditions/metadata
    case Preserve = 'preserve'; // Keep row, conditions, and metadata
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

// The guest session id is required; without it the action returns
// ['success' => false, 'itemsMerged' => 0, ...]
$result = $action->execute(user: $user, instance: 'default', sessionId: 'session-abc');
// $result['success'], $result['itemsMerged'], $result['message']
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

// Get rules for a key (returns an array of closures)
$rules = $factory->createRules('subtotal-at-least', ['amount' => 5000]);

// Check if key is supported
$factory->canCreateRules('subtotal-at-least'); // true

// Every supported key
$factory->getAvailableKeys();
```

### RulePresets

Static factory methods for common rules. Each returns an array of closures, and
`all()` / `any()` are variadic over those arrays.

```php
use AIArmada\Cart\Services\RulePresets;

// Value rules
$rules = RulePresets::minimumCartValue(5000);
$rules = RulePresets::maximumCartValue(100000);
$rules = RulePresets::cartValueBetween(1000, 10000);

// Quantity rules
$rules = RulePresets::minimumQuantity(3);
$rules = RulePresets::maximumQuantity(20);
$rules = RulePresets::minimumItems(2);
$rules = RulePresets::maximumItems(10);

// Product rules
$rules = RulePresets::requireProduct('SKU-1');
$rules = RulePresets::excludeProduct('SKU-1');
$rules = RulePresets::requireAnyProduct(['SKU-1', 'SKU-2']);
$rules = RulePresets::requireAllProducts(['SKU-1', 'SKU-2']);
$rules = RulePresets::requireProductPrefix('PROMO-');

// Time rules
$rules = RulePresets::dateRange('2024-01-01', '2024-01-31');
$rules = RulePresets::timeWindow('15:00', '17:00');
$rules = RulePresets::requireWeekday();
$rules = RulePresets::requireWeekend();
$rules = RulePresets::requireDaysOfWeek(['monday', 'wednesday']);

// Customer rules
$rules = RulePresets::requireCustomerTag('vip');
$rules = RulePresets::requireAnyCustomerTag(['gold', 'platinum']);
$rules = RulePresets::requireVip();
$rules = RulePresets::requireAuthenticated();

// Metadata rules
$rules = RulePresets::requireMetadata('coupon_code');
$rules = RulePresets::requireMetadataValue('channel', 'mobile');
$rules = RulePresets::requireFlag('first_order');
$rules = RulePresets::blockIfFlag('discount_used');

// Cart-state rules
$rules = RulePresets::requireNonEmpty();
$rules = RulePresets::blockIfConditionExists('OTHER-DISCOUNT');
$rules = RulePresets::requireCondition('SHIPPING-SELECTED');
$rules = RulePresets::blockIfConditionTypeExists('discount');
$rules = RulePresets::requireConditionType('tax');

// Combinators
$rules = RulePresets::always();
$rules = RulePresets::never();
$rules = RulePresets::all($rulesA, $rulesB);    // AND
$rules = RulePresets::any($rulesA, $rulesB);    // OR
$rules = RulePresets::not($rulesA);              // NOT
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
