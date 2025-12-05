# Carrier Selection Rules Engine

> **Document:** 4 of 9  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision

---

## Overview

Build an **intelligent rules engine** for automatic carrier selection based on package attributes, destination, performance metrics, and business priorities.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    SHIPMENT DATA                                 │
│  Package → Destination → Service Requirements → Priorities      │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                  CARRIER SELECTION ENGINE                        │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐   │
│  │ Rule         │  │ Capability   │  │ Performance          │   │
│  │ Evaluator    │  │ Filter       │  │ Scorer               │   │
│  └──────────────┘  └──────────────┘  └──────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────────┐│
│  │                    RULE SETS                                 ││
│  │  Priority 1: Force Carrier Rules                            ││
│  │  Priority 2: Restriction Rules                              ││
│  │  Priority 3: Preference Rules                               ││
│  │  Priority 4: Default Selection                              ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                  │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                  CARRIER SELECTION                               │
│  Selected Carrier → Fallback Options → Reasoning                │
└─────────────────────────────────────────────────────────────────┘
```

---

## Rule Types

### RuleType Enum

```php
enum RuleType: string
{
    case Force = 'force';           // Must use this carrier
    case Restrict = 'restrict';     // Cannot use this carrier
    case Prefer = 'prefer';         // Boost priority for carrier
    case Avoid = 'avoid';           // Reduce priority for carrier
    case Fallback = 'fallback';     // Use if primary unavailable
}
```

### ConditionType Enum

```php
enum ConditionType: string
{
    case WeightGreaterThan = 'weight_gt';
    case WeightLessThan = 'weight_lt';
    case ValueGreaterThan = 'value_gt';
    case ValueLessThan = 'value_lt';
    case DestinationZone = 'destination_zone';
    case DestinationState = 'destination_state';
    case DestinationPostcode = 'destination_postcode';
    case ServiceRequired = 'service_required';
    case IsCod = 'is_cod';
    case RequiresSignature = 'requires_signature';
    case ProductCategory = 'product_category';
    case CustomerTier = 'customer_tier';
    case TimeWindow = 'time_window';
}
```

---

## Rule Definition

### ShippingRule Model

```php
final class ShippingRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'type',
        'priority',
        'carrier_id',
        'conditions',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => RuleType::class,
            'conditions' => 'array',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && now()->gt($this->ends_at)) {
            return false;
        }

        return true;
    }
}
```

---

## Rule Condition

### RuleCondition Value Object

```php
final readonly class RuleCondition
{
    public function __construct(
        public ConditionType $type,
        public string $operator,
        public mixed $value,
        public ?string $unit = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: ConditionType::from($data['type']),
            operator: $data['operator'],
            value: $data['value'],
            unit: $data['unit'] ?? null,
        );
    }

    public function evaluate(ShipmentData $shipment): bool
    {
        return match ($this->type) {
            ConditionType::WeightGreaterThan => 
                $shipment->package->weightGrams > $this->value,
            
            ConditionType::WeightLessThan => 
                $shipment->package->weightGrams < $this->value,
            
            ConditionType::ValueGreaterThan => 
                ($shipment->declaredValue?->amountMinor ?? 0) > $this->value,
            
            ConditionType::DestinationState => 
                $this->matchesPattern($shipment->recipient->state),
            
            ConditionType::DestinationPostcode => 
                $this->matchesPattern($shipment->recipient->postcode),
            
            ConditionType::IsCod => 
                ($shipment->codAmount !== null) === $this->value,
            
            ConditionType::ServiceRequired => 
                in_array($this->value, $shipment->requiredServices ?? []),
            
            default => true,
        };
    }

    private function matchesPattern(string $value): bool
    {
        if (is_array($this->value)) {
            return in_array($value, $this->value);
        }

        if (str_contains($this->value, '*')) {
            return fnmatch($this->value, $value);
        }

        return $value === $this->value;
    }
}
```

---

## Carrier Selection Engine

### CarrierSelectionEngine Service

```php
final class CarrierSelectionEngine
{
    public function __construct(
        private readonly ShippingManager $manager,
        private readonly PerformanceScorer $scorer,
        private readonly RuleRepository $rules,
    ) {}

    /**
     * Select the best carrier for a shipment.
     */
    public function select(ShipmentData $shipment, ?array $carriers = null): CarrierSelection
    {
        $carriers ??= $this->manager->available();
        $applicableRules = $this->getApplicableRules($shipment);

        // Phase 1: Apply force rules
        $forced = $this->evaluateForceRules($applicableRules, $shipment);
        if ($forced) {
            return new CarrierSelection(
                carrierId: $forced,
                reason: 'Forced by rule',
                ruleId: $forced->ruleId,
            );
        }

        // Phase 2: Filter by restrictions
        $carriers = $this->filterByRestrictions($carriers, $applicableRules, $shipment);

        // Phase 3: Filter by capabilities
        $carriers = $this->filterByCapabilities($carriers, $shipment);

        if (empty($carriers)) {
            throw new NoCarrierAvailableException(
                'No carrier available for this shipment'
            );
        }

        // Phase 4: Score remaining carriers
        $scored = $this->scoreCarriers($carriers, $applicableRules, $shipment);

        // Phase 5: Select best option
        return $this->selectBest($scored);
    }

    private function getApplicableRules(ShipmentData $shipment): Collection
    {
        return $this->rules->active()
            ->orderBy('priority')
            ->get()
            ->filter(fn ($rule) => $this->allConditionsMet($rule, $shipment));
    }

    private function allConditionsMet(ShippingRule $rule, ShipmentData $shipment): bool
    {
        foreach ($rule->conditions as $conditionData) {
            $condition = RuleCondition::fromArray($conditionData);
            
            if (! $condition->evaluate($shipment)) {
                return false;
            }
        }

        return true;
    }

    private function filterByCapabilities(array $carriers, ShipmentData $shipment): array
    {
        return array_filter($carriers, function ($carrier) use ($shipment) {
            $capabilities = $carrier->getCapabilities();

            // Check COD support
            if ($shipment->codAmount && ! $capabilities->supportsCod) {
                return false;
            }

            // Check weight limit
            if ($shipment->package->weightGrams > $capabilities->maxPackageWeight) {
                return false;
            }

            // Check COD limit
            if ($shipment->codAmount && 
                $shipment->codAmount->amountMinor > $capabilities->maxCodValue) {
                return false;
            }

            return true;
        });
    }

    private function scoreCarriers(array $carriers, Collection $rules, ShipmentData $shipment): array
    {
        $scored = [];

        foreach ($carriers as $carrierId => $carrier) {
            $baseScore = 50; // Start at neutral

            // Apply preference rules
            foreach ($rules as $rule) {
                if ($rule->carrier_id !== $carrierId) {
                    continue;
                }

                $baseScore += match ($rule->type) {
                    RuleType::Prefer => 20,
                    RuleType::Avoid => -20,
                    default => 0,
                };
            }

            // Apply performance score
            $performanceScore = $this->scorer->getScore($carrierId, $shipment);
            $baseScore += $performanceScore;

            $scored[$carrierId] = [
                'carrier' => $carrier,
                'score' => $baseScore,
            ];
        }

        // Sort by score descending
        uasort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $scored;
    }
}
```

---

## Performance Scorer

### PerformanceScorer Service

```php
final class PerformanceScorer
{
    public function __construct(
        private readonly CarrierMetricsRepository $metrics,
    ) {}

    /**
     * Calculate performance score for a carrier (-25 to +25).
     */
    public function getScore(string $carrierId, ShipmentData $shipment): int
    {
        $zone = app(ZoneMapper::class)->getZone(
            $shipment->sender->postcode,
            $shipment->recipient->postcode
        );

        $metrics = $this->metrics->getForCarrierAndZone($carrierId, $zone);

        if (! $metrics) {
            return 0; // Neutral if no data
        }

        $score = 0;

        // Delivery success rate (max ±10)
        $score += $this->scoreDeliveryRate($metrics->delivery_success_rate);

        // On-time performance (max ±10)
        $score += $this->scoreOnTimeRate($metrics->on_time_rate);

        // Problem rate (max ±5)
        $score += $this->scoreProblemRate($metrics->problem_rate);

        return $score;
    }

    private function scoreDeliveryRate(float $rate): int
    {
        return match (true) {
            $rate >= 0.99 => 10,
            $rate >= 0.97 => 7,
            $rate >= 0.95 => 4,
            $rate >= 0.90 => 0,
            $rate >= 0.85 => -5,
            default => -10,
        };
    }

    private function scoreOnTimeRate(float $rate): int
    {
        return match (true) {
            $rate >= 0.95 => 10,
            $rate >= 0.90 => 7,
            $rate >= 0.85 => 4,
            $rate >= 0.80 => 0,
            $rate >= 0.70 => -5,
            default => -10,
        };
    }

    private function scoreProblemRate(float $rate): int
    {
        return match (true) {
            $rate <= 0.01 => 5,
            $rate <= 0.02 => 3,
            $rate <= 0.05 => 0,
            $rate <= 0.10 => -3,
            default => -5,
        };
    }
}
```

---

## Selection Result

### CarrierSelection Value Object

```php
final readonly class CarrierSelection
{
    public function __construct(
        public string $carrierId,
        public string $reason,
        public ?string $ruleId = null,
        public int $score = 0,
        public array $alternates = [],
        public array $reasoning = [],
    ) {}
}
```

---

## Example Rules

### Common Rule Configurations

```php
// Force J&T for East Malaysia
ShippingRule::create([
    'name' => 'Force J&T for East Malaysia',
    'type' => RuleType::Force,
    'priority' => 10,
    'carrier_id' => 'jnt',
    'conditions' => [
        ['type' => 'destination_state', 'operator' => 'in', 'value' => ['Sabah', 'Sarawak']],
    ],
]);

// Restrict DHL for COD orders
ShippingRule::create([
    'name' => 'No DHL for COD',
    'type' => RuleType::Restrict,
    'priority' => 20,
    'carrier_id' => 'dhl',
    'conditions' => [
        ['type' => 'is_cod', 'operator' => '=', 'value' => true],
    ],
]);

// Prefer Pos Laju for heavy items
ShippingRule::create([
    'name' => 'Prefer Pos Laju for Heavy',
    'type' => RuleType::Prefer,
    'priority' => 30,
    'carrier_id' => 'poslaju',
    'conditions' => [
        ['type' => 'weight_gt', 'operator' => '>', 'value' => 10000],
    ],
]);

// Avoid GDex for high-value items
ShippingRule::create([
    'name' => 'Avoid GDex for High Value',
    'type' => RuleType::Avoid,
    'priority' => 30,
    'carrier_id' => 'gdex',
    'conditions' => [
        ['type' => 'value_gt', 'operator' => '>', 'value' => 100000],
    ],
]);
```

---

## API Usage

```php
// Auto-select best carrier
$selection = Shipping::selectCarrier($shipmentData);

$shipment = Shipping::carrier($selection->carrierId)
    ->createShipment($shipmentData);

// With fallback
try {
    $selection = Shipping::selectCarrier($shipmentData);
    $shipment = Shipping::carrier($selection->carrierId)->createShipment($shipmentData);
} catch (CarrierException $e) {
    if ($alternate = $selection->alternates[0] ?? null) {
        $shipment = Shipping::carrier($alternate)->createShipment($shipmentData);
    }
}
```

---

## Navigation

**Previous:** [03-rate-shopping-engine.md](03-rate-shopping-engine.md)  
**Next:** [05-returns-reverse-logistics.md](05-returns-reverse-logistics.md)
