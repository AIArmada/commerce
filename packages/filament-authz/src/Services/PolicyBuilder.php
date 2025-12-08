<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Enums\ConditionOperator;
use AIArmada\FilamentAuthz\Enums\PolicyEffect;
use AIArmada\FilamentAuthz\Models\AccessPolicy;
use AIArmada\FilamentAuthz\ValueObjects\PolicyCondition;
use DateTimeInterface;
use Illuminate\Support\Str;

class PolicyBuilder
{
    protected string $name;

    protected ?string $description = null;

    protected PolicyEffect $effect = PolicyEffect::Allow;

    protected string $targetAction = '*';

    protected string $targetResource = '*';

    /** @var array<int, PolicyCondition> */
    protected array $conditions = [];

    protected int $priority = 0;

    protected bool $isActive = true;

    protected ?DateTimeInterface $validFrom = null;

    protected ?DateTimeInterface $validUntil = null;

    /** @var array<string, mixed>|null */
    protected ?array $metadata = null;

    /**
     * Start building a policy with a name.
     */
    public static function create(string $name): self
    {
        $builder = new self();
        $builder->name = $name;

        return $builder;
    }

    /**
     * Set the policy description.
     */
    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the policy effect to Allow.
     */
    public function allow(): self
    {
        $this->effect = PolicyEffect::Allow;

        return $this;
    }

    /**
     * Set the policy effect to Deny.
     */
    public function deny(): self
    {
        $this->effect = PolicyEffect::Deny;

        return $this;
    }

    /**
     * Set the target action.
     */
    public function action(string $action): self
    {
        $this->targetAction = $action;

        return $this;
    }

    /**
     * Set the target resource.
     */
    public function resource(string $resource): self
    {
        $this->targetResource = $resource;

        return $this;
    }

    /**
     * Target all actions on a resource.
     */
    public function allActionsOn(string $resource): self
    {
        $this->targetAction = '*';
        $this->targetResource = $resource;

        return $this;
    }

    /**
     * Target a specific action on all resources.
     */
    public function anyResource(string $action): self
    {
        $this->targetAction = $action;
        $this->targetResource = '*';

        return $this;
    }

    /**
     * Add a condition.
     */
    public function when(string $attribute, ConditionOperator $operator, mixed $value): self
    {
        $this->conditions[] = new PolicyCondition($attribute, $operator, $value);

        return $this;
    }

    /**
     * Add an equals condition.
     */
    public function whereEquals(string $attribute, mixed $value): self
    {
        return $this->when($attribute, ConditionOperator::Equals, $value);
    }

    /**
     * Add a not equals condition.
     */
    public function whereNotEquals(string $attribute, mixed $value): self
    {
        return $this->when($attribute, ConditionOperator::NotEquals, $value);
    }

    /**
     * Add a greater than condition.
     */
    public function whereGreaterThan(string $attribute, mixed $value): self
    {
        return $this->when($attribute, ConditionOperator::GreaterThan, $value);
    }

    /**
     * Add a less than condition.
     */
    public function whereLessThan(string $attribute, mixed $value): self
    {
        return $this->when($attribute, ConditionOperator::LessThan, $value);
    }

    /**
     * Add an "in" condition.
     *
     * @param  array<mixed>  $values
     */
    public function whereIn(string $attribute, array $values): self
    {
        return $this->when($attribute, ConditionOperator::In, $values);
    }

    /**
     * Add a "not in" condition.
     *
     * @param  array<mixed>  $values
     */
    public function whereNotIn(string $attribute, array $values): self
    {
        return $this->when($attribute, ConditionOperator::NotIn, $values);
    }

    /**
     * Add a contains condition.
     */
    public function whereContains(string $attribute, string $value): self
    {
        return $this->when($attribute, ConditionOperator::Contains, $value);
    }

    /**
     * Add a "starts with" condition.
     */
    public function whereStartsWith(string $attribute, string $value): self
    {
        return $this->when($attribute, ConditionOperator::StartsWith, $value);
    }

    /**
     * Add a "between" condition.
     */
    public function whereBetween(string $attribute, mixed $min, mixed $max): self
    {
        return $this->when($attribute, ConditionOperator::Between, [$min, $max]);
    }

    /**
     * Add a null check condition.
     */
    public function whereNull(string $attribute): self
    {
        return $this->when($attribute, ConditionOperator::IsNull, null);
    }

    /**
     * Add a not null check condition.
     */
    public function whereNotNull(string $attribute): self
    {
        return $this->when($attribute, ConditionOperator::IsNotNull, null);
    }

    /**
     * Add a regex match condition.
     */
    public function whereMatches(string $attribute, string $pattern): self
    {
        return $this->when($attribute, ConditionOperator::Matches, $pattern);
    }

    /**
     * Add a condition for owner check.
     */
    public function whereOwner(): self
    {
        return $this->whereEquals('resource.user_id', '@user.id');
    }

    /**
     * Add a condition for team membership.
     */
    public function whereTeamMember(): self
    {
        return $this->when('user.team_ids', ConditionOperator::Contains, '@resource.team_id');
    }

    /**
     * Add a condition for role check.
     */
    public function whereHasRole(string $role): self
    {
        return $this->when('user.roles', ConditionOperator::Contains, $role);
    }

    /**
     * Add a condition for IP range.
     *
     * @param  array<string>  $ipRanges
     */
    public function whereIpInRange(array $ipRanges): self
    {
        return $this->whereIn('request.ip', $ipRanges);
    }

    /**
     * Add a condition for business hours (9 AM - 5 PM).
     */
    public function duringBusinessHours(): self
    {
        return $this->whereBetween('request.hour', 9, 17);
    }

    /**
     * Set the priority.
     */
    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Set as high priority.
     */
    public function highPriority(): self
    {
        return $this->priority(100);
    }

    /**
     * Set as low priority.
     */
    public function lowPriority(): self
    {
        return $this->priority(-100);
    }

    /**
     * Set the policy as inactive.
     */
    public function inactive(): self
    {
        $this->isActive = false;

        return $this;
    }

    /**
     * Set validity period.
     */
    public function validBetween(DateTimeInterface $from, DateTimeInterface $until): self
    {
        $this->validFrom = $from;
        $this->validUntil = $until;

        return $this;
    }

    /**
     * Set valid from date.
     */
    public function validFrom(DateTimeInterface $from): self
    {
        $this->validFrom = $from;

        return $this;
    }

    /**
     * Set valid until date.
     */
    public function validUntil(DateTimeInterface $until): self
    {
        $this->validUntil = $until;

        return $this;
    }

    /**
     * Add metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Build and save the policy.
     */
    public function save(): AccessPolicy
    {
        return AccessPolicy::create([
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'description' => $this->description,
            'effect' => $this->effect,
            'target_action' => $this->targetAction,
            'target_resource' => $this->targetResource,
            'conditions' => array_map(fn (PolicyCondition $c): array => $c->toArray(), $this->conditions),
            'priority' => $this->priority,
            'is_active' => $this->isActive,
            'valid_from' => $this->validFrom,
            'valid_until' => $this->validUntil,
            'metadata' => $this->metadata,
        ]);
    }

    /**
     * Build without saving (for testing/preview).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'description' => $this->description,
            'effect' => $this->effect->value,
            'target_action' => $this->targetAction,
            'target_resource' => $this->targetResource,
            'conditions' => array_map(fn (PolicyCondition $c): array => $c->toArray(), $this->conditions),
            'priority' => $this->priority,
            'is_active' => $this->isActive,
            'valid_from' => $this->validFrom?->format('Y-m-d H:i:s'),
            'valid_until' => $this->validUntil?->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata,
        ];
    }
}
