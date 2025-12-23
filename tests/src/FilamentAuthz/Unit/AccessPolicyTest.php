<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\PolicyDecision;
use AIArmada\FilamentAuthz\Enums\PolicyEffect;
use AIArmada\FilamentAuthz\Models\AccessPolicy;

beforeEach(function (): void {
    // Drop and recreate access policies table
    Schema::dropIfExists('authz_access_policies');
    Schema::create('authz_access_policies', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('slug')->unique();
        $table->text('description')->nullable();
        $table->string('effect');
        $table->string('target_action');
        $table->string('target_resource')->nullable();
        $table->json('conditions')->nullable();
        $table->integer('priority')->default(0);
        $table->boolean('is_active')->default(true);
        $table->timestamp('valid_from')->nullable();
        $table->timestamp('valid_until')->nullable();
        $table->json('metadata')->nullable();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('authz_access_policies');
});

test('can create access policy', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Test Policy',
        'slug' => 'test-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 1,
    ]);

    expect($policy)->toBeInstanceOf(AccessPolicy::class)
        ->and($policy->name)->toBe('Test Policy')
        ->and($policy->slug)->toBe('test-policy');
});

test('getEffectEnum returns correct enum', function (): void {
    $allowPolicy = AccessPolicy::create([
        'name' => 'Allow Policy',
        'slug' => 'allow-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
    ]);

    $denyPolicy = AccessPolicy::create([
        'name' => 'Deny Policy',
        'slug' => 'deny-policy',
        'effect' => 'deny',
        'target_action' => 'orders.view',
        'is_active' => true,
    ]);

    expect($allowPolicy->getEffectEnum())->toBe(PolicyEffect::Allow)
        ->and($denyPolicy->getEffectEnum())->toBe(PolicyEffect::Deny);
});

test('getEffectEnum defaults to deny for invalid value', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Invalid Policy',
        'slug' => 'invalid-policy',
        'effect' => 'invalid',
        'target_action' => 'orders.view',
        'is_active' => true,
    ]);

    expect($policy->getEffectEnum())->toBe(PolicyEffect::Deny);
});

test('isValid returns true for active policy without dates', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Active Policy',
        'slug' => 'active-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
    ]);

    expect($policy->isValid())->toBeTrue();
});

test('isValid returns false for inactive policy', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Inactive Policy',
        'slug' => 'inactive-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => false,
    ]);

    expect($policy->isValid())->toBeFalse();
});

test('isValid returns false for future policy', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Future Policy',
        'slug' => 'future-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
        'valid_from' => now()->addDay(),
    ]);

    expect($policy->isValid())->toBeFalse();
});

test('isValid returns false for expired policy', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Expired Policy',
        'slug' => 'expired-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
        'valid_until' => now()->subDay(),
    ]);

    expect($policy->isValid())->toBeFalse();
});

test('isValid returns true for policy within date range', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Valid Range Policy',
        'slug' => 'valid-range-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
        'valid_from' => now()->subDay(),
        'valid_until' => now()->addDay(),
    ]);

    expect($policy->isValid())->toBeTrue();
});

test('appliesTo matches exact action', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Exact Action Policy',
        'slug' => 'exact-action-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
    ]);

    expect($policy->appliesTo('orders.view'))->toBeTrue()
        ->and($policy->appliesTo('orders.create'))->toBeFalse();
});

test('appliesTo matches wildcard action', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Wildcard Action Policy',
        'slug' => 'wildcard-action-policy',
        'effect' => 'allow',
        'target_action' => '*',
        'is_active' => true,
    ]);

    expect($policy->appliesTo('orders.view'))->toBeTrue()
        ->and($policy->appliesTo('users.create'))->toBeTrue();
});

test('appliesTo matches prefix wildcard action', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Prefix Wildcard Policy',
        'slug' => 'prefix-wildcard-policy',
        'effect' => 'allow',
        'target_action' => 'orders.*',
        'is_active' => true,
    ]);

    expect($policy->appliesTo('orders.view'))->toBeTrue()
        ->and($policy->appliesTo('orders.create'))->toBeTrue()
        ->and($policy->appliesTo('users.view'))->toBeFalse();
});

test('appliesTo matches exact resource', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Exact Resource Policy',
        'slug' => 'exact-resource-policy',
        'effect' => 'allow',
        'target_action' => 'view',
        'target_resource' => 'orders',
        'is_active' => true,
    ]);

    expect($policy->appliesTo('view', 'orders'))->toBeTrue()
        ->and($policy->appliesTo('view', 'users'))->toBeFalse();
});

test('appliesTo matches wildcard resource', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Wildcard Resource Policy',
        'slug' => 'wildcard-resource-policy',
        'effect' => 'allow',
        'target_action' => 'view',
        'target_resource' => '*',
        'is_active' => true,
    ]);

    expect($policy->appliesTo('view', 'orders'))->toBeTrue()
        ->and($policy->appliesTo('view', 'users'))->toBeTrue();
});

test('appliesTo matches null target resource', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'No Resource Policy',
        'slug' => 'no-resource-policy',
        'effect' => 'allow',
        'target_action' => 'view',
        'target_resource' => null,
        'is_active' => true,
    ]);

    expect($policy->appliesTo('view', 'anything'))->toBeTrue();
});

test('evaluate returns permit for valid allow policy', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Allow Policy',
        'slug' => 'allow-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
    ]);

    $decision = $policy->evaluate([]);

    expect($decision)->toBe(PolicyDecision::Permit);
});

test('evaluate returns deny for valid deny policy', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Deny Policy',
        'slug' => 'deny-policy',
        'effect' => 'deny',
        'target_action' => 'orders.view',
        'is_active' => true,
    ]);

    $decision = $policy->evaluate([]);

    expect($decision)->toBe(PolicyDecision::Deny);
});

test('evaluate returns not applicable for invalid policy', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Inactive Policy',
        'slug' => 'inactive-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => false,
    ]);

    $decision = $policy->evaluate([]);

    expect($decision)->toBe(PolicyDecision::NotApplicable);
});

test('evaluateConditions returns true when no conditions', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'No Conditions Policy',
        'slug' => 'no-conditions-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
        'conditions' => null,
    ]);

    expect($policy->evaluateConditions([]))->toBeTrue();
});

test('evaluateConditions with eq operator', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Eq Condition Policy',
        'slug' => 'eq-condition-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
        'conditions' => [
            ['attribute' => 'role', 'operator' => 'eq', 'value' => 'admin'],
        ],
    ]);

    expect($policy->evaluateConditions(['role' => 'admin']))->toBeTrue()
        ->and($policy->evaluateConditions(['role' => 'user']))->toBeFalse();
});

test('evaluateConditions with multiple conditions', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Multiple Conditions Policy',
        'slug' => 'multiple-conditions-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
        'conditions' => [
            ['attribute' => 'role', 'operator' => 'eq', 'value' => 'admin'],
            ['attribute' => 'active', 'operator' => 'eq', 'value' => true],
        ],
    ]);

    // Both conditions must pass
    expect($policy->evaluateConditions(['role' => 'admin', 'active' => true]))->toBeTrue()
        ->and($policy->evaluateConditions(['role' => 'admin', 'active' => false]))->toBeFalse()
        ->and($policy->evaluateConditions(['role' => 'user', 'active' => true]))->toBeFalse();
});

test('evaluateConditions with source context', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Source Context Policy',
        'slug' => 'source-context-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
        'conditions' => [
            ['attribute' => 'department', 'operator' => 'eq', 'value' => 'sales', 'source' => 'subject'],
        ],
    ]);

    $context = [
        'subject' => ['department' => 'sales'],
    ];

    expect($policy->evaluateConditions($context))->toBeTrue();
});

test('scopeActive returns only active policies', function (): void {
    AccessPolicy::create([
        'name' => 'Active Policy',
        'slug' => 'active-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
    ]);

    AccessPolicy::create([
        'name' => 'Inactive Policy',
        'slug' => 'inactive-policy',
        'effect' => 'allow',
        'target_action' => 'orders.create',
        'is_active' => false,
    ]);

    $activePolicies = AccessPolicy::active()->get();

    expect($activePolicies)->toHaveCount(1)
        ->and($activePolicies->first()->name)->toBe('Active Policy');
});

test('scopeCurrentlyValid filters by date range', function (): void {
    AccessPolicy::create([
        'name' => 'Valid Policy',
        'slug' => 'valid-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
        'valid_from' => now()->subDay(),
        'valid_until' => now()->addDay(),
    ]);

    AccessPolicy::create([
        'name' => 'Expired Policy',
        'slug' => 'expired-policy',
        'effect' => 'allow',
        'target_action' => 'orders.create',
        'is_active' => true,
        'valid_until' => now()->subDay(),
    ]);

    $validPolicies = AccessPolicy::currentlyValid()->get();

    expect($validPolicies)->toHaveCount(1)
        ->and($validPolicies->first()->name)->toBe('Valid Policy');
});

test('scopeForAction filters by action', function (): void {
    AccessPolicy::create([
        'name' => 'View Policy',
        'slug' => 'view-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
    ]);

    AccessPolicy::create([
        'name' => 'Create Policy',
        'slug' => 'create-policy',
        'effect' => 'allow',
        'target_action' => 'orders.create',
        'is_active' => true,
    ]);

    $viewPolicies = AccessPolicy::forAction('orders.view')->get();

    expect($viewPolicies)->toHaveCount(1)
        ->and($viewPolicies->first()->name)->toBe('View Policy');
});

test('scopeForAction includes wildcard policies', function (): void {
    AccessPolicy::create([
        'name' => 'Specific Policy',
        'slug' => 'specific-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
    ]);

    AccessPolicy::create([
        'name' => 'Wildcard Policy',
        'slug' => 'wildcard-policy',
        'effect' => 'allow',
        'target_action' => '*',
        'is_active' => true,
    ]);

    $policies = AccessPolicy::forAction('orders.view')->get();

    expect($policies)->toHaveCount(2);
});

test('scopeForAction includes prefix wildcard policies', function (): void {
    AccessPolicy::create([
        'name' => 'Orders Wildcard Policy',
        'slug' => 'orders-wildcard-policy',
        'effect' => 'allow',
        'target_action' => 'orders.*',
        'is_active' => true,
    ]);

    $policies = AccessPolicy::forAction('orders.view')->get();

    expect($policies)->toHaveCount(1);
});

test('scopeForResource filters by resource', function (): void {
    AccessPolicy::create([
        'name' => 'Orders Policy',
        'slug' => 'orders-policy',
        'effect' => 'allow',
        'target_action' => 'view',
        'target_resource' => 'orders',
        'is_active' => true,
    ]);

    AccessPolicy::create([
        'name' => 'Users Policy',
        'slug' => 'users-policy',
        'effect' => 'allow',
        'target_action' => 'view',
        'target_resource' => 'users',
        'is_active' => true,
    ]);

    $ordersPolicies = AccessPolicy::forResource('orders')->get();

    expect($ordersPolicies)->toHaveCount(1)
        ->and($ordersPolicies->first()->name)->toBe('Orders Policy');
});

test('scopeForResource includes null resource policies', function (): void {
    AccessPolicy::create([
        'name' => 'No Resource Policy',
        'slug' => 'no-resource-policy',
        'effect' => 'allow',
        'target_action' => 'view',
        'target_resource' => null,
        'is_active' => true,
    ]);

    $policies = AccessPolicy::forResource('orders')->get();

    expect($policies)->toHaveCount(1);
});

test('scopeOrderByPriority orders by priority', function (): void {
    AccessPolicy::create([
        'name' => 'Low Priority',
        'slug' => 'low-priority',
        'effect' => 'allow',
        'target_action' => 'view',
        'is_active' => true,
        'priority' => 1,
    ]);

    AccessPolicy::create([
        'name' => 'High Priority',
        'slug' => 'high-priority',
        'effect' => 'allow',
        'target_action' => 'view',
        'is_active' => true,
        'priority' => 10,
    ]);

    $policies = AccessPolicy::orderByPriority()->get();

    expect($policies->first()->name)->toBe('High Priority')
        ->and($policies->last()->name)->toBe('Low Priority');
});

test('casts work correctly', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Cast Test Policy',
        'slug' => 'cast-test-policy',
        'effect' => 'allow',
        'target_action' => 'view',
        'is_active' => '1',
        'priority' => '5',
        'conditions' => [['attribute' => 'role', 'operator' => 'eq', 'value' => 'admin']],
        'metadata' => ['key' => 'value'],
        'valid_from' => '2024-01-01 00:00:00',
    ]);

    expect($policy->is_active)->toBeBool()
        ->and($policy->priority)->toBeInt()
        ->and($policy->conditions)->toBeArray()
        ->and($policy->metadata)->toBeArray()
        ->and($policy->valid_from)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});

test('scopeForOwner filters by owner when enabled', function (): void {
    config(['filament-authz.owner.enabled' => true]);
    config(['filament-authz.owner.include_global' => true]);

    $owner = User::create([
        'name' => 'Owner User',
        'email' => 'owner@example.com',
        'password' => bcrypt('password'),
    ]);

    \AIArmada\CommerceSupport\Support\OwnerContext::withOwner(null, function (): void {
        AccessPolicy::create([
            'name' => 'Global Policy',
            'slug' => 'global-policy',
            'effect' => 'allow',
            'target_action' => 'view',
            'is_active' => true,
            'owner_type' => null,
            'owner_id' => null,
        ]);
    });

    \AIArmada\CommerceSupport\Support\OwnerContext::withOwner($owner, function () use ($owner): void {
        AccessPolicy::create([
            'name' => 'Owned Policy',
            'slug' => 'owned-policy',
            'effect' => 'allow',
            'target_action' => 'view',
            'is_active' => true,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
        ]);
    });

    $policies = AccessPolicy::forOwner($owner)->get();

    expect($policies)->toHaveCount(2); // Owned + global
});

test('scopeForOwner returns all when disabled', function (): void {
    config(['filament-authz.owner.enabled' => false]);

    AccessPolicy::create([
        'name' => 'Policy 1',
        'slug' => 'policy-1',
        'effect' => 'allow',
        'target_action' => 'view',
        'is_active' => true,
    ]);

    AccessPolicy::create([
        'name' => 'Policy 2',
        'slug' => 'policy-2',
        'effect' => 'allow',
        'target_action' => 'view',
        'is_active' => true,
    ]);

    $policies = AccessPolicy::forOwner(null)->get();

    expect($policies)->toHaveCount(2);
});
