# Targeting Engine

> **Document:** 03-targeting-engine.md  
> **Status:** Vision  
> **Priority:** P1

---

## Current State

The vouchers package has basic targeting via the `target_definition` JSON column and `VoucherCondition::validateVoucher()`:

```php
// Current validation in VoucherCondition
public function validateVoucher(Cart $cart, ?CartItem $item = null): bool
{
    $validationResult = Voucher::validate($this->voucher->code, $cart);
    return $validationResult->isValid;
}
```

Current validation checks:
- ✅ Voucher exists and is active
- ✅ Date range (starts_at, expires_at)
- ✅ Usage limits (global, per-user)
- ✅ Minimum cart value
- ❌ User segments
- ❌ Product/category restrictions
- ❌ Geographic rules
- ❌ Device/channel targeting
- ❌ Complex boolean logic

---

## Vision: Intelligent Targeting Engine

### 3.1 Targeting Rule Types

```php
enum TargetingRuleType: string
{
    // User-based
    case UserSegment = 'user_segment';
    case UserAttribute = 'user_attribute';
    case FirstPurchase = 'first_purchase';
    case CustomerLifetimeValue = 'clv';
    
    // Cart-based
    case CartValue = 'cart_value';
    case CartQuantity = 'cart_quantity';
    case ProductInCart = 'product_in_cart';
    case CategoryInCart = 'category_in_cart';
    
    // Time-based
    case TimeWindow = 'time_window';
    case DayOfWeek = 'day_of_week';
    case DateRange = 'date_range';
    
    // Context-based
    case Channel = 'channel';
    case Device = 'device';
    case Geographic = 'geographic';
    case Referrer = 'referrer';
}
```

### 3.2 Rule Configuration Schema

```json
{
  "targeting": {
    "mode": "all",
    "rules": [
      {
        "type": "user_segment",
        "operator": "in",
        "values": ["vip", "gold_member"]
      },
      {
        "type": "cart_value",
        "operator": ">=",
        "value": 10000
      },
      {
        "type": "category_in_cart",
        "operator": "contains_any",
        "values": ["electronics", "appliances"]
      },
      {
        "type": "time_window",
        "operator": "between",
        "start": "18:00",
        "end": "23:59",
        "timezone": "Asia/Kuala_Lumpur"
      }
    ]
  }
}
```

### 3.3 Boolean Logic Support

```json
{
  "targeting": {
    "mode": "custom",
    "expression": {
      "and": [
        {
          "or": [
            { "type": "user_segment", "operator": "in", "values": ["vip"] },
            { "type": "first_purchase", "operator": "=", "value": true }
          ]
        },
        { "type": "cart_value", "operator": ">=", "value": 5000 },
        {
          "not": {
            "type": "product_in_cart", "operator": "contains", "values": ["EXCLUDED-SKU"]
          }
        }
      ]
    }
  }
}
```

---

## Architecture

### Targeting Context

```php
class TargetingContext
{
    public function __construct(
        public readonly Cart $cart,
        public readonly ?Model $user,
        public readonly ?Request $request,
        public readonly array $metadata = []
    ) {}
    
    public function getUserSegments(): array
    {
        if ($this->user === null) {
            return ['guest'];
        }
        
        return $this->user->segments ?? [];
    }
    
    public function getCartValue(): int
    {
        return $this->cart->getRawSubtotalWithoutConditions();
    }
    
    public function getProductSkus(): array
    {
        return $this->cart->getItems()
            ->pluck('buyable.sku')
            ->filter()
            ->toArray();
    }
    
    public function getChannel(): string
    {
        return $this->request?->header('X-Channel') ?? 'web';
    }
    
    public function getTimezone(): string
    {
        return $this->metadata['timezone'] ?? config('app.timezone');
    }
}
```

### Rule Evaluator Interface

```php
interface TargetingRuleEvaluator
{
    public function supports(string $type): bool;
    
    public function evaluate(
        array $rule,
        TargetingContext $context
    ): bool;
}
```

### Built-in Evaluators

```php
class UserSegmentEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === 'user_segment';
    }
    
    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $userSegments = $context->getUserSegments();
        $targetSegments = $rule['values'] ?? [];
        
        return match ($rule['operator']) {
            'in' => !empty(array_intersect($userSegments, $targetSegments)),
            'not_in' => empty(array_intersect($userSegments, $targetSegments)),
            'all' => empty(array_diff($targetSegments, $userSegments)),
            default => false,
        };
    }
}

class CartValueEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === 'cart_value';
    }
    
    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $cartValue = $context->getCartValue();
        $targetValue = $rule['value'] ?? 0;
        
        return match ($rule['operator']) {
            '>=' => $cartValue >= $targetValue,
            '>' => $cartValue > $targetValue,
            '<=' => $cartValue <= $targetValue,
            '<' => $cartValue < $targetValue,
            '=' => $cartValue === $targetValue,
            'between' => $cartValue >= $rule['min'] && $cartValue <= $rule['max'],
            default => false,
        };
    }
}

class TimeWindowEvaluator implements TargetingRuleEvaluator
{
    public function supports(string $type): bool
    {
        return $type === 'time_window';
    }
    
    public function evaluate(array $rule, TargetingContext $context): bool
    {
        $timezone = $rule['timezone'] ?? $context->getTimezone();
        $now = Carbon::now($timezone);
        
        $start = Carbon::parse($rule['start'], $timezone);
        $end = Carbon::parse($rule['end'], $timezone);
        
        return $now->between($start, $end);
    }
}
```

### Targeting Engine

```php
class TargetingEngine
{
    /** @var array<TargetingRuleEvaluator> */
    private array $evaluators = [];
    
    public function registerEvaluator(TargetingRuleEvaluator $evaluator): void
    {
        $this->evaluators[] = $evaluator;
    }
    
    public function evaluate(array $targeting, TargetingContext $context): bool
    {
        $mode = $targeting['mode'] ?? 'all';
        
        return match ($mode) {
            'all' => $this->evaluateAll($targeting['rules'] ?? [], $context),
            'any' => $this->evaluateAny($targeting['rules'] ?? [], $context),
            'custom' => $this->evaluateExpression($targeting['expression'] ?? [], $context),
            default => true,
        };
    }
    
    private function evaluateAll(array $rules, TargetingContext $context): bool
    {
        foreach ($rules as $rule) {
            if (!$this->evaluateRule($rule, $context)) {
                return false;
            }
        }
        return true;
    }
    
    private function evaluateAny(array $rules, TargetingContext $context): bool
    {
        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $context)) {
                return true;
            }
        }
        return false;
    }
    
    private function evaluateExpression(array $expression, TargetingContext $context): bool
    {
        if (isset($expression['and'])) {
            return $this->evaluateAll($expression['and'], $context);
        }
        
        if (isset($expression['or'])) {
            return $this->evaluateAny($expression['or'], $context);
        }
        
        if (isset($expression['not'])) {
            return !$this->evaluateRule($expression['not'], $context);
        }
        
        // Single rule
        return $this->evaluateRule($expression, $context);
    }
}
```

---

## Integration with VoucherCondition

```php
class VoucherCondition implements CartConditionConvertible
{
    public function validateVoucher(Cart $cart, ?CartItem $item = null): bool
    {
        // Basic validation
        $validationResult = Voucher::validate($this->voucher->code, $cart);
        
        if (!$validationResult->isValid) {
            return false;
        }
        
        // Targeting validation
        if ($this->voucher->targeting !== null) {
            $context = new TargetingContext(
                cart: $cart,
                user: auth()->user(),
                request: request(),
            );
            
            return app(TargetingEngine::class)
                ->evaluate($this->voucher->targeting, $context);
        }
        
        return true;
    }
}
```

---

## Database Evolution

### New Columns

```php
Schema::table('vouchers', function (Blueprint $table) {
    // Replace simple target_definition with rich targeting
    $table->jsonb('targeting')->nullable();
    
    // Indexes for common targeting queries
    $table->index([
        DB::raw("(targeting->'rules'->0->>'type')"),
    ], 'idx_vouchers_targeting_type');
});
```

### Migration from target_definition

```php
// Migrate existing target_definition to new targeting format
Voucher::whereNotNull('target_definition')->chunk(100, function ($vouchers) {
    foreach ($vouchers as $voucher) {
        $voucher->targeting = [
            'mode' => 'all',
            'rules' => $this->convertLegacyTarget($voucher->target_definition),
        ];
        $voucher->save();
    }
});
```

---

## Filament Integration

### Visual Rule Builder

```php
// Filament form component for targeting rules
Forms\Components\Repeater::make('targeting.rules')
    ->schema([
        Forms\Components\Select::make('type')
            ->options(TargetingRuleType::options())
            ->reactive(),
        Forms\Components\Select::make('operator')
            ->options(fn (Get $get) => $this->getOperatorsForType($get('type'))),
        Forms\Components\TagsInput::make('values')
            ->visible(fn (Get $get) => $this->requiresValues($get('type'))),
        Forms\Components\TextInput::make('value')
            ->numeric()
            ->visible(fn (Get $get) => $this->requiresSingleValue($get('type'))),
    ])
    ->collapsible()
    ->itemLabel(fn (array $state) => $this->formatRuleLabel($state));
```

---

## Implementation Phases

### Phase 1: Core Engine
- [ ] `TargetingContext` value object
- [ ] `TargetingRuleEvaluator` interface
- [ ] `TargetingEngine` orchestrator
- [ ] Basic evaluators (cart_value, user_segment)

### Phase 2: Evaluators
- [ ] Time-based evaluators
- [ ] Product/category evaluators
- [ ] Geographic evaluator
- [ ] Channel/device evaluators

### Phase 3: Boolean Logic
- [ ] AND/OR/NOT expression parser
- [ ] Nested expression evaluation
- [ ] Expression validation

### Phase 4: Filament UI
- [ ] Visual rule builder
- [ ] Rule preview/testing
- [ ] Targeting analytics

---

## Navigation

**Previous:** [02-advanced-voucher-types.md](02-advanced-voucher-types.md)  
**Next:** [04-gift-card-system.md](04-gift-card-system.md)
