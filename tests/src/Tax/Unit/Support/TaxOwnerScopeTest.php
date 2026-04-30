<?php

declare(strict_types=1);

namespace AIArmada\Tax\Tests\Unit\Support;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Tax\TaxTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Support\TaxOwnerScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaxOwnerScopeTest extends TaxTestCase
{
    use RefreshDatabase;

    private function bindOwner(?Model $owner): void
    {
        app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
        {
            public function __construct(private ?Model $owner) {}

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });
    }

    public function test_is_enabled_returns_config_value(): void
    {
        config(['tax.features.owner.enabled' => true]);
        $this->assertTrue(TaxOwnerScope::isEnabled());

        config(['tax.features.owner.enabled' => false]);
        $this->assertFalse(TaxOwnerScope::isEnabled());
    }

    public function test_include_global_returns_config_value(): void
    {
        config(['tax.features.owner.include_global' => true]);
        $this->assertTrue(TaxOwnerScope::includeGlobal());

        config(['tax.features.owner.include_global' => false]);
        $this->assertFalse(TaxOwnerScope::includeGlobal());
    }

    public function test_resolve_owner_returns_null_when_disabled(): void
    {
        config(['tax.features.owner.enabled' => false]);

        $owner = User::query()->create([
            'name' => 'Test Owner',
            'email' => 'test-resolve@example.com',
            'password' => 'secret',
        ]);

        $this->bindOwner($owner);

        $this->assertNull(TaxOwnerScope::resolveOwner());
    }

    public function test_resolve_owner_returns_owner_when_enabled(): void
    {
        config(['tax.features.owner.enabled' => true]);

        $owner = User::query()->create([
            'name' => 'Test Owner',
            'email' => 'test-resolve-enabled@example.com',
            'password' => 'secret',
        ]);

        $this->bindOwner($owner);

        $resolved = TaxOwnerScope::resolveOwner();

        $this->assertNotNull($resolved);
        $this->assertEquals($owner->id, $resolved->id);
    }

    public function test_apply_to_owned_query_returns_unmodified_when_disabled(): void
    {
        config(['tax.features.owner.enabled' => false]);

        $this->bindOwner(null);

        TaxZone::create([
            'name' => 'Zone 1',
            'code' => 'Z1',
            'is_active' => true,
        ]);

        TaxZone::create([
            'name' => 'Zone 2',
            'code' => 'Z2',
            'is_active' => true,
        ]);

        $query = TaxOwnerScope::applyToOwnedQuery(TaxZone::query());
        $zones = $query->get();

        $this->assertCount(2, $zones);
    }

    public function test_apply_to_owned_query_scopes_to_owner_when_enabled(): void
    {
        config(['tax.features.owner.enabled' => true]);
        config(['tax.features.owner.include_global' => false]);

        $owner = User::query()->create([
            'name' => 'Query Owner',
            'email' => 'query-owner@example.com',
            'password' => 'secret',
        ]);

        OwnerContext::withOwner(null, fn () => TaxZone::create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL-Q',
            'is_active' => true,
        ]));

        $this->bindOwner($owner);

        TaxZone::create([
            'name' => 'Owned Zone',
            'code' => 'OWNED-Q',
            'is_active' => true,
        ]);

        $query = TaxOwnerScope::applyToOwnedQuery(TaxZone::query());
        $zones = $query->get();

        $this->assertCount(1, $zones);
        $this->assertEquals('Owned Zone', $zones->first()->name);
    }

    public function test_apply_to_owned_query_includes_global_when_configured(): void
    {
        config(['tax.features.owner.enabled' => true]);
        config(['tax.features.owner.include_global' => true]);

        $owner = User::query()->create([
            'name' => 'Include Global Owner',
            'email' => 'include-global@example.com',
            'password' => 'secret',
        ]);

        OwnerContext::withOwner(null, fn () => TaxZone::create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL-INC',
            'is_active' => true,
        ]));

        $this->bindOwner($owner);

        TaxZone::create([
            'name' => 'Owned Zone',
            'code' => 'OWNED-INC',
            'is_active' => true,
        ]);

        $query = TaxOwnerScope::applyToOwnedQuery(TaxZone::query());
        $zones = $query->get();

        $this->assertCount(2, $zones);
        $this->assertTrue($zones->pluck('name')->contains('Global Zone'));
        $this->assertTrue($zones->pluck('name')->contains('Owned Zone'));
    }

    public function test_apply_to_owned_query_with_null_owner_returns_global_only(): void
    {
        config(['tax.features.owner.enabled' => true]);
        config(['tax.features.owner.include_global' => false]);

        $owner = User::query()->create([
            'name' => 'Null Test Owner',
            'email' => 'null-test@example.com',
            'password' => 'secret',
        ]);

        OwnerContext::withOwner(null, fn () => TaxZone::create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL-NULL',
            'is_active' => true,
        ]));

        $this->bindOwner($owner);

        TaxZone::create([
            'name' => 'Owned Zone',
            'code' => 'OWNED-NULL',
            'is_active' => true,
        ]);

        $this->bindOwner(null);

        $query = TaxOwnerScope::applyToOwnedQuery(TaxZone::query());
        $zones = $query->get();

        $this->assertCount(1, $zones);
        $this->assertEquals('Global Zone', $zones->first()->name);
    }
}
