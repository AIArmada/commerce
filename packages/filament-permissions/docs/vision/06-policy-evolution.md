# Policy Evolution & ABAC

> **Document:** 6 of 10  
> **Package:** `aiarmada/filament-permissions`  
> **Status:** Vision

---

## Overview

Evolve from simple RBAC to **Attribute-Based Access Control (ABAC)** with dynamic, condition-based policies that evaluate user attributes, resource properties, and environmental context.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    POLICY EVOLUTION LAYERS                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Level 1: RBAC (Current)                                        │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  User ──▶ Role ──▶ Permissions                              │ │
│  └────────────────────────────────────────────────────────────┘ │
│                            │                                     │
│                            ▼                                     │
│  Level 2: RBAC + Policies                                       │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  User ──▶ Role ──▶ Permissions ──▶ Laravel Policy          │ │
│  └────────────────────────────────────────────────────────────┘ │
│                            │                                     │
│                            ▼                                     │
│  Level 3: ABAC (Vision)                                         │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  Subject ──▶ Policy Engine ──▶ Decision                    │ │
│  │      │                │                                     │ │
│  │      ▼                ▼                                     │ │
│  │  Attributes      Conditions      Environment                │ │
│  │  (user, role)    (rules)         (time, IP, device)         │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## ABAC Components

### PolicyCondition Value Object

```php
final readonly class PolicyCondition
{
    public function __construct(
        public string $attribute,
        public ConditionOperator $operator,
        public mixed $value,
        public ?string $source = 'subject', // subject, resource, environment
    ) {}

    public function evaluate(array $context): bool
    {
        $actualValue = data_get($context, "{$this->source}.{$this->attribute}");

        return match ($this->operator) {
            ConditionOperator::Equals => $actualValue === $this->value,
            ConditionOperator::NotEquals => $actualValue !== $this->value,
            ConditionOperator::GreaterThan => $actualValue > $this->value,
            ConditionOperator::LessThan => $actualValue < $this->value,
            ConditionOperator::Contains => in_array($this->value, (array) $actualValue),
            ConditionOperator::In => in_array($actualValue, (array) $this->value),
            ConditionOperator::Matches => preg_match($this->value, $actualValue),
            ConditionOperator::Between => $actualValue >= $this->value[0] && $actualValue <= $this->value[1],
            ConditionOperator::IsNull => is_null($actualValue),
            ConditionOperator::IsNotNull => ! is_null($actualValue),
        };
    }

    public function toArray(): array
    {
        return [
            'attribute' => $this->attribute,
            'operator' => $this->operator->value,
            'value' => $this->value,
            'source' => $this->source,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            attribute: $data['attribute'],
            operator: ConditionOperator::from($data['operator']),
            value: $data['value'],
            source: $data['source'] ?? 'subject',
        );
    }
}

enum ConditionOperator: string
{
    case Equals = 'eq';
    case NotEquals = 'neq';
    case GreaterThan = 'gt';
    case LessThan = 'lt';
    case Contains = 'contains';
    case In = 'in';
    case Matches = 'matches';
    case Between = 'between';
    case IsNull = 'is_null';
    case IsNotNull = 'is_not_null';
}
```

---

## Policy Model

### AccessPolicy Model

```php
final class AccessPolicy extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'effect',
        'target_action',
        'target_resource',
        'conditions',
        'priority',
        'is_active',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'effect' => PolicyEffect::class,
            'conditions' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('filament-permissions.database.tables.access_policies', 'access_policies');
    }

    /**
     * Get hydrated conditions.
     * 
     * @return array<PolicyCondition>
     */
    public function getConditions(): array
    {
        return array_map(
            fn (array $c) => PolicyCondition::fromArray($c),
            $this->conditions ?? []
        );
    }

    /**
     * Check if this policy applies to the given action/resource.
     */
    public function appliesTo(string $action, ?string $resource = null): bool
    {
        if ($this->target_action !== '*' && $this->target_action !== $action) {
            return false;
        }

        if ($resource && $this->target_resource !== '*' && $this->target_resource !== $resource) {
            return false;
        }

        return true;
    }

    /**
     * Check if policy is currently valid.
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }

        return true;
    }

    /**
     * Evaluate this policy against a context.
     */
    public function evaluate(array $context): PolicyDecision
    {
        if (! $this->isValid()) {
            return PolicyDecision::NotApplicable;
        }

        foreach ($this->getConditions() as $condition) {
            if (! $condition->evaluate($context)) {
                return PolicyDecision::NotApplicable;
            }
        }

        return match ($this->effect) {
            PolicyEffect::Allow => PolicyDecision::Allow,
            PolicyEffect::Deny => PolicyDecision::Deny,
        };
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()));
    }

    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where(fn ($q) => $q
            ->where('target_action', $action)
            ->orWhere('target_action', '*')
        );
    }
}

enum PolicyEffect: string
{
    case Allow = 'allow';
    case Deny = 'deny';
}

enum PolicyDecision: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case NotApplicable = 'not_applicable';
}
```

---

## Policy Engine

### PolicyEngine

```php
final class PolicyEngine
{
    public function __construct(
        private readonly PolicyCombiningAlgorithm $algorithm = PolicyCombiningAlgorithm::DenyOverrides,
    ) {}

    /**
     * Evaluate access request against all policies.
     */
    public function evaluate(
        User $subject,
        string $action,
        ?Model $resource = null,
        array $environment = []
    ): PolicyDecision {
        $context = $this->buildContext($subject, $resource, $environment);

        $policies = AccessPolicy::query()
            ->active()
            ->forAction($action)
            ->orderBy('priority', 'desc')
            ->get();

        if ($policies->isEmpty()) {
            return PolicyDecision::NotApplicable;
        }

        $decisions = $policies->map(fn ($policy) => $policy->evaluate($context));

        return $this->combineDecisions($decisions);
    }

    /**
     * Check if user can perform action.
     */
    public function can(User $subject, string $action, ?Model $resource = null): bool
    {
        $decision = $this->evaluate($subject, $action, $resource);

        return $decision === PolicyDecision::Allow;
    }

    /**
     * Build context for policy evaluation.
     */
    private function buildContext(User $subject, ?Model $resource, array $environment): array
    {
        return [
            'subject' => [
                'id' => $subject->id,
                'email' => $subject->email,
                'roles' => $subject->getRoleNames()->toArray(),
                'permissions' => $subject->getAllPermissions()->pluck('name')->toArray(),
                'department' => $subject->department ?? null,
                'is_verified' => $subject->hasVerifiedEmail(),
                'created_at' => $subject->created_at,
            ],
            'resource' => $resource ? [
                'id' => $resource->getKey(),
                'type' => get_class($resource),
                'owner_id' => $resource->user_id ?? $resource->owner_id ?? null,
                'status' => $resource->status ?? null,
                'created_at' => $resource->created_at ?? null,
            ] : [],
            'environment' => array_merge([
                'time' => now(),
                'day_of_week' => now()->dayOfWeek,
                'hour' => now()->hour,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'is_api' => request()?->expectsJson(),
            ], $environment),
        ];
    }

    /**
     * Combine multiple decisions using the configured algorithm.
     */
    private function combineDecisions(Collection $decisions): PolicyDecision
    {
        return match ($this->algorithm) {
            PolicyCombiningAlgorithm::DenyOverrides => $this->denyOverrides($decisions),
            PolicyCombiningAlgorithm::AllowOverrides => $this->allowOverrides($decisions),
            PolicyCombiningAlgorithm::FirstApplicable => $this->firstApplicable($decisions),
            PolicyCombiningAlgorithm::UnanimousAllow => $this->unanimousAllow($decisions),
        };
    }

    private function denyOverrides(Collection $decisions): PolicyDecision
    {
        if ($decisions->contains(PolicyDecision::Deny)) {
            return PolicyDecision::Deny;
        }

        if ($decisions->contains(PolicyDecision::Allow)) {
            return PolicyDecision::Allow;
        }

        return PolicyDecision::NotApplicable;
    }

    private function allowOverrides(Collection $decisions): PolicyDecision
    {
        if ($decisions->contains(PolicyDecision::Allow)) {
            return PolicyDecision::Allow;
        }

        if ($decisions->contains(PolicyDecision::Deny)) {
            return PolicyDecision::Deny;
        }

        return PolicyDecision::NotApplicable;
    }

    private function firstApplicable(Collection $decisions): PolicyDecision
    {
        return $decisions->first(
            fn ($d) => $d !== PolicyDecision::NotApplicable,
            PolicyDecision::NotApplicable
        );
    }

    private function unanimousAllow(Collection $decisions): PolicyDecision
    {
        $applicable = $decisions->filter(fn ($d) => $d !== PolicyDecision::NotApplicable);

        if ($applicable->isEmpty()) {
            return PolicyDecision::NotApplicable;
        }

        return $applicable->every(fn ($d) => $d === PolicyDecision::Allow)
            ? PolicyDecision::Allow
            : PolicyDecision::Deny;
    }
}

enum PolicyCombiningAlgorithm: string
{
    case DenyOverrides = 'deny_overrides';
    case AllowOverrides = 'allow_overrides';
    case FirstApplicable = 'first_applicable';
    case UnanimousAllow = 'unanimous_allow';
}
```

---

## Policy Builder DSL

### PolicyBuilder

```php
final class PolicyBuilder
{
    private string $name;
    private ?string $description = null;
    private PolicyEffect $effect = PolicyEffect::Allow;
    private string $targetAction = '*';
    private ?string $targetResource = null;
    private array $conditions = [];
    private int $priority = 0;

    public static function create(string $name): self
    {
        $builder = new self();
        $builder->name = $name;

        return $builder;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function allow(): self
    {
        $this->effect = PolicyEffect::Allow;

        return $this;
    }

    public function deny(): self
    {
        $this->effect = PolicyEffect::Deny;

        return $this;
    }

    public function forAction(string $action): self
    {
        $this->targetAction = $action;

        return $this;
    }

    public function forResource(string $resource): self
    {
        $this->targetResource = $resource;

        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function when(string $attribute, ConditionOperator $operator, mixed $value, string $source = 'subject'): self
    {
        $this->conditions[] = new PolicyCondition($attribute, $operator, $value, $source);

        return $this;
    }

    public function whereSubject(string $attribute, ConditionOperator $operator, mixed $value): self
    {
        return $this->when($attribute, $operator, $value, 'subject');
    }

    public function whereResource(string $attribute, ConditionOperator $operator, mixed $value): self
    {
        return $this->when($attribute, $operator, $value, 'resource');
    }

    public function whereEnvironment(string $attribute, ConditionOperator $operator, mixed $value): self
    {
        return $this->when($attribute, $operator, $value, 'environment');
    }

    public function duringBusinessHours(): self
    {
        return $this->whereEnvironment('hour', ConditionOperator::Between, [9, 17]);
    }

    public function onWeekdays(): self
    {
        return $this->whereEnvironment('day_of_week', ConditionOperator::Between, [1, 5]);
    }

    public function forOwner(): self
    {
        // Subject ID must match resource owner_id
        return $this->when('id', ConditionOperator::Equals, '${resource.owner_id}', 'subject');
    }

    public function withRole(string $role): self
    {
        return $this->whereSubject('roles', ConditionOperator::Contains, $role);
    }

    public function save(): AccessPolicy
    {
        return AccessPolicy::create([
            'name' => $this->name,
            'description' => $this->description,
            'effect' => $this->effect,
            'target_action' => $this->targetAction,
            'target_resource' => $this->targetResource,
            'conditions' => array_map(fn ($c) => $c->toArray(), $this->conditions),
            'priority' => $this->priority,
            'is_active' => true,
        ]);
    }
}
```

---

## Example Policies

```php
// Allow users to view only their own orders
PolicyBuilder::create('view-own-orders')
    ->description('Users can view their own orders')
    ->allow()
    ->forAction('view')
    ->forResource('Order')
    ->forOwner()
    ->save();

// Deny access outside business hours for non-admins
PolicyBuilder::create('business-hours-only')
    ->description('Non-admins can only access during business hours')
    ->deny()
    ->forAction('*')
    ->whereSubject('roles', ConditionOperator::NotEquals, 'admin')
    ->whereEnvironment('hour', ConditionOperator::NotEquals, [9, 17])
    ->priority(100) // High priority
    ->save();

// Allow managers to approve pending orders
PolicyBuilder::create('managers-approve-pending')
    ->description('Managers can approve pending orders')
    ->allow()
    ->forAction('approve')
    ->forResource('Order')
    ->withRole('manager')
    ->whereResource('status', ConditionOperator::Equals, 'pending')
    ->save();
```

---

## Gate Integration

### ABAC Gate Registration

```php
// In ServiceProvider
Gate::before(function (User $user, string $ability, array $arguments = []) {
    $policyEngine = app(PolicyEngine::class);

    $resource = $arguments[0] ?? null;

    $decision = $policyEngine->evaluate(
        subject: $user,
        action: $ability,
        resource: $resource instanceof Model ? $resource : null,
    );

    if ($decision === PolicyDecision::Allow) {
        return true;
    }

    if ($decision === PolicyDecision::Deny) {
        return false;
    }

    // NotApplicable - let standard RBAC/policies handle it
    return null;
});
```

---

## Navigation

**Previous:** [05-audit-trail.md](05-audit-trail.md)  
**Next:** [07-permission-simulation.md](07-permission-simulation.md)
