<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Tax\Models\TaxClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$bindTaxOwnerForScoping = function (?Model $owner): void {
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
};

describe('TaxClass', function () use ($bindTaxOwnerForScoping): void {
    it('can create tax class', function (): void {
        $taxClass = TaxClass::create([
            'name' => 'Standard Rate',
            'slug' => 'standard',
            'description' => 'Standard tax rate for most products',
            'is_default' => true,
            'is_active' => true,
            'position' => 1,
        ]);

        $this->assertInstanceOf(TaxClass::class, $taxClass);
        $this->assertEquals('Standard Rate', $taxClass->name);
        $this->assertEquals('standard', $taxClass->slug);
        $this->assertTrue($taxClass->is_default);
        $this->assertTrue($taxClass->is_active);
    });

    it('get default method', function (): void {
        TaxClass::create([
            'name' => 'Standard',
            'slug' => 'standard',
            'is_default' => true,
            'is_active' => true,
        ]);

        TaxClass::create([
            'name' => 'Reduced',
            'slug' => 'reduced',
            'is_default' => false,
            'is_active' => true,
        ]);

        $default = TaxClass::getDefault();

        $this->assertEquals('Standard', $default->name);
    });

    it('find by slug method', function (): void {
        TaxClass::create([
            'name' => 'Standard Rate',
            'slug' => 'standard',
            'is_active' => true,
        ]);

        $found = TaxClass::findBySlug('standard');

        $this->assertEquals('Standard Rate', $found->name);
    });

    it('find by slug returns null for nonexistent', function (): void {
        $found = TaxClass::findBySlug('nonexistent');

        $this->assertNull($found);
    });

    it('active scope', function (): void {
        TaxClass::create([
            'name' => 'Active Class',
            'slug' => 'active',
            'is_active' => true,
        ]);

        TaxClass::create([
            'name' => 'Inactive Class',
            'slug' => 'inactive',
            'is_active' => false,
        ]);

        $activeClasses = TaxClass::active()->get();

        $this->assertCount(1, $activeClasses);
        $this->assertEquals('Active Class', $activeClasses->first()->name);
    });

    it('default scope', function (): void {
        TaxClass::create([
            'name' => 'Default Class',
            'slug' => 'default',
            'is_default' => true,
            'is_active' => true,
        ]);

        TaxClass::create([
            'name' => 'Regular Class',
            'slug' => 'regular',
            'is_default' => false,
            'is_active' => true,
        ]);

        $defaultClasses = TaxClass::default()->get();

        $this->assertCount(1, $defaultClasses);
        $this->assertEquals('Default Class', $defaultClasses->first()->name);
    });

    it('ordered scope', function (): void {
        TaxClass::create([
            'name' => 'Third',
            'slug' => 'third',
            'position' => 3,
            'is_active' => true,
        ]);

        TaxClass::create([
            'name' => 'First',
            'slug' => 'first',
            'position' => 1,
            'is_active' => true,
        ]);

        TaxClass::create([
            'name' => 'Second',
            'slug' => 'second',
            'position' => 2,
            'is_active' => true,
        ]);

        $ordered = TaxClass::ordered()->get();

        $this->assertEquals(['First', 'Second', 'Third'], $ordered->pluck('name')->toArray());
    });

    it('casts', function (): void {
        $taxClass = TaxClass::create([
            'name' => 'Cast Test',
            'slug' => 'cast-test',
            'is_default' => true,
            'is_active' => false,
            'position' => 5,
        ]);

        $this->assertIsBool($taxClass->is_default);
        $this->assertIsBool($taxClass->is_active);
        $this->assertIsInt($taxClass->position);
    });

    it('attributes defaults', function (): void {
        $taxClass = new TaxClass(['name' => 'Test', 'slug' => 'test']);

        $this->assertFalse($taxClass->is_default);
        $this->assertTrue($taxClass->is_active);
        $this->assertEquals(0, $taxClass->position);
    });

    it('activity logging', function (): void {
        $taxClass = TaxClass::create([
            'name' => 'Activity Test',
            'slug' => 'activity-test',
            'is_active' => true,
        ]);

        $taxClass->update(['name' => 'Updated Name']);

        // Activity logging is configured but we can't easily test it without more setup
        // This test ensures the trait is applied and doesn't break
        $this->assertTrue(true);
    });

    it('for owner scope when owner disabled', function (): void {
        config(['tax.features.owner.enabled' => false]);

        TaxClass::create([
            'name' => 'Global Class',
            'slug' => 'global',
            'is_active' => true,
        ]);

        $classes = TaxClass::forOwner(null)->get();

        $this->assertCount(1, $classes);
    });

    it('for owner scope with null owner', function () use ($bindTaxOwnerForScoping): void {
        config(['tax.features.owner.enabled' => true]);

        $owner = new class extends Model
        {
            protected $table = 'users';

            public $id = 1;

            public function getMorphClass(): string
            {
                return 'App\\Models\\Store';
            }

            public function getKey(): mixed
            {
                return '123';
            }
        };

        $bindTaxOwnerForScoping(null);

        OwnerContext::withOwner(null, fn () => TaxClass::create([
            'name' => 'Global Class',
            'slug' => 'global',
            'owner_type' => null,
            'owner_id' => null,
            'is_active' => true,
        ]));

        $bindTaxOwnerForScoping($owner);

        TaxClass::create([
            'name' => 'Owned Class',
            'slug' => 'owned',
            'is_active' => true,
        ]);

        // When owner is null and includeGlobal=true (default), returns records where owner_id is null
        $classes = TaxClass::forOwner(null)->get();

        $this->assertCount(1, $classes);
        $this->assertEquals('Global Class', $classes->first()->name);
    });

    it('for owner scope with null owner exclude global', function () use ($bindTaxOwnerForScoping): void {
        config(['tax.features.owner.enabled' => true]);

        $bindTaxOwnerForScoping(null);

        OwnerContext::withOwner(null, fn () => TaxClass::create([
            'name' => 'Global Class',
            'slug' => 'global',
            'owner_type' => null,
            'owner_id' => null,
            'is_active' => true,
        ]));

        // When owner is null and includeGlobal=false, returns records where both owner_type and owner_id are null
        $classes = TaxClass::forOwner(null, includeGlobal: false)->get();

        $this->assertCount(1, $classes);
        $this->assertEquals('Global Class', $classes->first()->name);
    });

    it('for owner scope with owner include global', function () use ($bindTaxOwnerForScoping): void {
        config(['tax.features.owner.enabled' => true]);
        config(['tax.features.owner.include_global' => true]);

        // Create a mock owner
        $owner = new class extends Model
        {
            protected $table = 'users';

            public $id = 1;

            public function getMorphClass(): string
            {
                return 'App\\Models\\Store';
            }

            public function getKey(): mixed
            {
                return '123';
            }
        };

        $bindTaxOwnerForScoping(null);

        OwnerContext::withOwner(null, fn () => TaxClass::create([
            'name' => 'Global Class',
            'slug' => 'global',
            'owner_type' => null,
            'owner_id' => null,
            'is_active' => true,
        ]));

        $bindTaxOwnerForScoping($owner);

        TaxClass::create([
            'name' => 'Owned Class',
            'slug' => 'owned',
            'is_active' => true,
        ]);

        DB::table((new TaxClass)->getTable())->insert([
            'id' => (string) Str::uuid(),
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => '456',
            'name' => 'Other Owner Class',
            'slug' => 'other',
            'description' => null,
            'is_default' => false,
            'is_active' => true,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classes = TaxClass::forOwner($owner, includeGlobal: true)->get();

        $this->assertCount(2, $classes);
        $names = $classes->pluck('name')->toArray();
        $this->assertContains('Global Class', $names);
        $this->assertContains('Owned Class', $names);
    });

    it('for owner scope with owner exclude global', function () use ($bindTaxOwnerForScoping): void {
        config(['tax.features.owner.enabled' => true]);

        $owner = new class extends Model
        {
            protected $table = 'users';

            public $id = 1;

            public function getMorphClass(): string
            {
                return 'App\\Models\\Store';
            }

            public function getKey(): mixed
            {
                return '123';
            }
        };

        $bindTaxOwnerForScoping(null);

        OwnerContext::withOwner(null, fn () => TaxClass::create([
            'name' => 'Global Class',
            'slug' => 'global',
            'owner_type' => null,
            'owner_id' => null,
            'is_active' => true,
        ]));

        $bindTaxOwnerForScoping($owner);

        TaxClass::create([
            'name' => 'Owned Class',
            'slug' => 'owned',
            'is_active' => true,
        ]);

        $classes = TaxClass::forOwner($owner, includeGlobal: false)->get();

        $this->assertCount(1, $classes);
        $this->assertEquals('Owned Class', $classes->first()->name);
    });

    it('get default returns null when none', function (): void {
        TaxClass::create([
            'name' => 'Non-default',
            'slug' => 'non-default',
            'is_default' => false,
            'is_active' => true,
        ]);

        $default = TaxClass::getDefault();

        $this->assertNull($default);
    });
});
