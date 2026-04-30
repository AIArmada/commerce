<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Models\Condition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Ensure conditions table exists for model tests
    if (! Schema::hasTable('conditions')) {
        Schema::create('conditions', function ($table): void {
            $table->uuid('id')->primary();
            $table->string('owner_scope')->default('global');
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('target');
            $table->json('target_definition')->nullable();
            $table->string('value');
            $table->string('operator')->nullable();
            $table->boolean('is_charge')->default(false);
            $table->boolean('is_dynamic')->default(false);
            $table->boolean('is_discount')->default(false);
            $table->boolean('is_percentage')->default(false);
            $table->string('parsed_value')->nullable();
            $table->integer('order')->default(0);
            $table->json('attributes')->nullable();
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_global')->default(false);
            $table->nullableUuidMorphs('owner');
            $table->timestamps();
            $table->unique(['owner_scope', 'name']);
        });
    }
});

describe('Condition Model Instantiation', function (): void {
    it('can be instantiated with required fields', function (): void {
        $condition = Condition::create([
            'name' => 'test_condition',
            'display_name' => 'Test Condition',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
        ]);

        expect($condition)->toBeInstanceOf(Condition::class);
        expect($condition->name)->toBe('test_condition');
        expect($condition->display_name)->toBe('Test Condition');
        expect($condition->type)->toBe('discount');
        expect($condition->target)->toBe('cart@cart_subtotal/aggregate');
        expect($condition->value)->toBe('-10%');
    });

    it('uses uuid as primary key', function (): void {
        $condition = Condition::create([
            'name' => 'uuid_test',
            'type' => 'tax',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '10%',
        ]);

        expect($condition->id)->toBeString();
        expect(mb_strlen($condition->id))->toBe(36); // UUID format
    });

    it('uses custom table name from config', function (): void {
        $condition = new Condition;

        expect($condition->getTable())->toBe(config('cart.database.conditions_table', 'conditions'));
    });
});

describe('Computed Derived Fields', function (): void {
    it('computes percentage discount fields correctly', function (): void {
        $condition = Condition::create([
            'name' => 'percentage_discount',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-25%',
        ]);

        expect($condition->operator)->toBe('%');
        expect($condition->is_percentage)->toBeTrue();
        expect($condition->is_discount)->toBeTrue();
        expect($condition->is_charge)->toBeFalse();
        expect($condition->parsed_value)->toBe('-0.25');
    });

    it('computes percentage charge fields correctly', function (): void {
        $condition = Condition::create([
            'name' => 'percentage_charge',
            'type' => 'tax',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '15%',
        ]);

        expect($condition->operator)->toBe('%');
        expect($condition->is_percentage)->toBeTrue();
        expect($condition->is_discount)->toBeFalse();
        expect($condition->is_charge)->toBeTrue();
        expect($condition->parsed_value)->toBe('0.15');
    });

    it('computes fixed addition fields correctly', function (): void {
        $condition = Condition::create([
            'name' => 'fixed_addition',
            'type' => 'fee',
            'target' => 'cart@grand_total/aggregate',
            'value' => '+5.99',
        ]);

        expect($condition->operator)->toBe('+');
        expect($condition->is_percentage)->toBeFalse();
        expect($condition->is_discount)->toBeFalse();
        expect($condition->is_charge)->toBeTrue();
        expect($condition->parsed_value)->toBe('5.99');
    });

    it('computes fixed subtraction fields correctly', function (): void {
        $condition = Condition::create([
            'name' => 'fixed_subtraction',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10.00',
        ]);

        expect($condition->operator)->toBe('-');
        expect($condition->is_percentage)->toBeFalse();
        expect($condition->is_discount)->toBeTrue();
        expect($condition->is_charge)->toBeFalse();
        expect($condition->parsed_value)->toBe('-10.00');
    });

    it('computes multiplication fields correctly', function (): void {
        $condition = Condition::create([
            'name' => 'multiply',
            'type' => 'modifier',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '*1.5',
        ]);

        expect($condition->operator)->toBe('*');
        expect($condition->is_percentage)->toBeFalse();
        expect($condition->parsed_value)->toBe('1.5');
    });

    it('computes division fields correctly', function (): void {
        $condition = Condition::create([
            'name' => 'divide',
            'type' => 'modifier',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '/2',
        ]);

        expect($condition->operator)->toBe('/');
        expect($condition->is_percentage)->toBeFalse();
        expect($condition->parsed_value)->toBe('2');
    });

    it('defaults to addition when no operator specified', function (): void {
        $condition = Condition::create([
            'name' => 'no_operator',
            'type' => 'fee',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '25.00',
        ]);

        expect($condition->operator)->toBe('+');
        expect($condition->is_percentage)->toBeFalse();
        expect($condition->is_charge)->toBeTrue();
        expect($condition->parsed_value)->toBe('25.00');
    });
});

describe('Type Check Methods', function (): void {
    it('identifies discount correctly', function (): void {
        $condition = Condition::create([
            'name' => 'discount_check',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
        ]);

        expect($condition->isDiscount())->toBeTrue();
        expect($condition->isCharge())->toBeFalse();
    });

    it('identifies charge correctly', function (): void {
        $condition = Condition::create([
            'name' => 'charge_check',
            'type' => 'tax',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '10%',
        ]);

        expect($condition->isCharge())->toBeTrue();
        expect($condition->isDiscount())->toBeFalse();
    });

    it('identifies fee type correctly', function (): void {
        $feeCondition = Condition::create([
            'name' => 'fee_check',
            'type' => 'fee',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '+5.00',
        ]);

        $surchargeCondition = Condition::create([
            'name' => 'surcharge_check',
            'type' => 'surcharge',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '+3.00',
        ]);

        expect($feeCondition->isFee())->toBeTrue();
        expect($surchargeCondition->isFee())->toBeTrue();
    });

    it('identifies tax type correctly', function (): void {
        $condition = Condition::create([
            'name' => 'tax_check',
            'type' => 'tax',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '8%',
        ]);

        expect($condition->isTax())->toBeTrue();
        expect($condition->isFee())->toBeFalse();
    });

    it('identifies shipping type correctly', function (): void {
        $condition = Condition::create([
            'name' => 'shipping_check',
            'type' => 'shipping',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '+15.00',
        ]);

        expect($condition->isShipping())->toBeTrue();
        expect($condition->isTax())->toBeFalse();
    });

    it('identifies percentage correctly', function (): void {
        $percentCondition = Condition::create([
            'name' => 'percent_check',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-20%',
        ]);

        $fixedCondition = Condition::create([
            'name' => 'fixed_check',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-20.00',
        ]);

        expect($percentCondition->isPercentage())->toBeTrue();
        expect($fixedCondition->isPercentage())->toBeFalse();
    });

    it('identifies global correctly', function (): void {
        $globalCondition = Condition::create([
            'name' => 'global_check',
            'type' => 'tax',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '10%',
            'is_global' => true,
        ]);

        $nonGlobalCondition = Condition::create([
            'name' => 'non_global_check',
            'type' => 'tax',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '10%',
            'is_global' => false,
        ]);

        expect($globalCondition->isGlobal())->toBeTrue();
        expect($nonGlobalCondition->isGlobal())->toBeFalse();
    });
});

describe('Dynamic Conditions and Rules', function (): void {
    it('identifies static condition (no rules)', function (): void {
        $condition = Condition::create([
            'name' => 'static_condition',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
        ]);

        expect($condition->isDynamic())->toBeFalse();
        expect($condition->getRuleFactoryKeys())->toBe([]);
        expect($condition->getRuleContext())->toBe([]);
    });

    it('identifies dynamic condition with rules', function (): void {
        $condition = Condition::create([
            'name' => 'dynamic_condition',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
            'rules' => [
                'factory_keys' => ['min_cart_total'],
                'context' => ['min_total' => 100],
            ],
        ]);

        expect($condition->isDynamic())->toBeTrue();
        expect($condition->getRuleFactoryKeys())->toBe(['min_cart_total']);
        expect($condition->getRuleContext())->toBe(['min_total' => 100]);
    });

    it('returns empty arrays when rules is not array', function (): void {
        $condition = new Condition;
        $condition->rules = null;

        expect($condition->getRuleFactoryKeys())->toBe([]);
        expect($condition->getRuleContext())->toBe([]);
    });

    it('filters empty factory keys', function (): void {
        $condition = Condition::create([
            'name' => 'filter_empty_keys',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-5%',
            'rules' => [
                'factory_keys' => ['valid_key', '', 'another_key'],
                'context' => [],
            ],
        ]);

        expect($condition->getRuleFactoryKeys())->toBe(['valid_key', 'another_key']);
    });
});

describe('Normalize Rules Definition', function (): void {
    it('returns null for non-dynamic conditions', function (): void {
        $result = Condition::normalizeRulesDefinition(['factory_keys' => ['test']], false);
        expect($result)->toBeNull();
    });

    it('returns null for empty rules', function (): void {
        $result = Condition::normalizeRulesDefinition([], true);
        expect($result)->toBeNull();
    });

    it('returns null when factory_keys is empty after filtering', function (): void {
        $result = Condition::normalizeRulesDefinition(['factory_keys' => ['', '']], true);
        expect($result)->toBeNull();
    });

    it('normalizes valid rules with factory keys', function (): void {
        $result = Condition::normalizeRulesDefinition([
            'factory_keys' => ['key1', 'key2'],
            'context' => ['param' => 'value'],
        ], true);

        expect($result)->toBe([
            'factory_keys' => ['key1', 'key2'],
            'context' => ['param' => 'value'],
        ]);
    });

    it('filters non-string factory keys', function (): void {
        $result = Condition::normalizeRulesDefinition([
            'factory_keys' => ['valid', 123, null, 'another'],
        ], true);

        expect($result['factory_keys'])->toBe(['valid', 'another']);
    });

    it('skips empty context keys', function (): void {
        $result = Condition::normalizeRulesDefinition([
            'factory_keys' => ['test'],
            'context' => ['' => 'skip_this', 'valid' => 'keep_this'],
        ], true);

        expect($result['context'])->toBe(['valid' => 'keep_this']);
    });

    it('normalizes string boolean values in context', function (): void {
        $result = Condition::normalizeRulesDefinition([
            'factory_keys' => ['test'],
            'context' => [
                'is_enabled' => 'true',
                'is_disabled' => 'false',
                'is_TRUE' => 'TRUE',
                'is_FALSE' => 'FALSE',
            ],
        ], true);

        expect($result['context']['is_enabled'])->toBeTrue();
        expect($result['context']['is_disabled'])->toBeFalse();
        expect($result['context']['is_TRUE'])->toBeTrue();
        expect($result['context']['is_FALSE'])->toBeFalse();
    });

    it('normalizes numeric strings in context', function (): void {
        $result = Condition::normalizeRulesDefinition([
            'factory_keys' => ['test'],
            'context' => [
                'integer' => '42',
                'float' => '3.14',
                'exponent' => '1e5',
            ],
        ], true);

        expect($result['context']['integer'])->toBe(42);
        expect($result['context']['float'])->toBe(3.14);
        expect($result['context']['exponent'])->toBe(100000.0);
    });

    it('normalizes JSON strings in context', function (): void {
        $result = Condition::normalizeRulesDefinition([
            'factory_keys' => ['test'],
            'context' => [
                'array' => '["a", "b", "c"]',
                'object' => '{"key": "value"}',
            ],
        ], true);

        expect($result['context']['array'])->toBe(['a', 'b', 'c']);
        expect($result['context']['object'])->toBe(['key' => 'value']);
    });

    it('normalizes comma-separated strings to arrays', function (): void {
        $result = Condition::normalizeRulesDefinition([
            'factory_keys' => ['test'],
            'context' => [
                'list' => 'a, b, c',
                'numbers' => '1, 2, 3',
            ],
        ], true);

        expect($result['context']['list'])->toBe(['a', 'b', 'c']);
        expect($result['context']['numbers'])->toBe([1, 2, 3]);
    });

    it('trims whitespace from string values', function (): void {
        $result = Condition::normalizeRulesDefinition([
            'factory_keys' => ['test'],
            'context' => [
                'padded' => '  value  ',
            ],
        ], true);

        expect($result['context']['padded'])->toBe('value');
    });

    it('returns null for empty trimmed strings', function (): void {
        $result = Condition::normalizeRulesDefinition([
            'factory_keys' => ['test'],
            'context' => [
                'empty' => '   ',
            ],
        ], true);

        expect($result['context'])->not->toHaveKey('empty');
    });

    it('preserves boolean values as-is', function (): void {
        $result = Condition::normalizeRulesDefinition([
            'factory_keys' => ['test'],
            'context' => [
                'bool_true' => true,
                'bool_false' => false,
            ],
        ], true);

        expect($result['context']['bool_true'])->toBeTrue();
        expect($result['context']['bool_false'])->toBeFalse();
    });

    it('preserves numeric values as-is', function (): void {
        $result = Condition::normalizeRulesDefinition([
            'factory_keys' => ['test'],
            'context' => [
                'int' => 42,
                'float' => 3.14,
            ],
        ], true);

        expect($result['context']['int'])->toBe(42);
        expect($result['context']['float'])->toBe(3.14);
    });

    it('recursively normalizes array values', function (): void {
        $result = Condition::normalizeRulesDefinition([
            'factory_keys' => ['test'],
            'context' => [
                'nested' => ['value1', '42', 'true'],
            ],
        ], true);

        expect($result['context']['nested'])->toBe(['value1', 42, true]);
    });
});

describe('Formatted Value Attribute', function (): void {
    it('returns percentage value as-is', function (): void {
        $condition = Condition::create([
            'name' => 'format_percent',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-15%',
        ]);

        expect($condition->formatted_value)->toBe('-15%');
    });

    it('formats fixed positive value with currency', function (): void {
        $condition = Condition::create([
            'name' => 'format_positive',
            'type' => 'fee',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '+2599',
        ]);

        $formatted = $condition->formatted_value;
        expect($formatted)->toContain('+');
        // Value is in cents, formatted as currency
        expect($formatted)->toContain('$');
    });

    it('formats fixed negative value with currency', function (): void {
        $condition = Condition::create([
            'name' => 'format_negative',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10.00',
        ]);

        $formatted = $condition->formatted_value;
        expect($formatted)->toContain('10');
    });
});

describe('To Condition Array', function (): void {
    it('converts model to condition array', function (): void {
        $condition = Condition::create([
            'name' => 'array_test',
            'display_name' => 'Array Test Display',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
            'order' => 5,
            'attributes' => ['custom' => 'value'],
            'is_global' => true,
        ]);

        $array = $condition->toConditionArray();

        expect($array['name'])->toBe('Array Test Display');
        expect($array['type'])->toBe('discount');
        expect($array['target'])->toBe('cart@cart_subtotal/aggregate');
        expect($array['value'])->toBe('-10%');
        expect($array['order'])->toBe(5);
        // Note: The toConditionArray method merges internal attributes with condition metadata
        // It includes condition_id, condition_name, and is_global from the model
        expect($array['attributes'])->toBeArray();
        expect($array['attributes']['condition_id'])->toBe($condition->id);
        expect($array['attributes']['condition_name'])->toBe('array_test');
        expect($array['is_global'])->toBeTrue();
    });

    it('uses custom name when provided', function (): void {
        $condition = Condition::create([
            'name' => 'custom_name_test',
            'display_name' => 'Original Display Name',
            'type' => 'tax',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '10%',
        ]);

        $array = $condition->toConditionArray('Custom Override Name');

        expect($array['name'])->toBe('Custom Override Name');
    });

    it('includes rules for dynamic conditions', function (): void {
        $condition = Condition::create([
            'name' => 'dynamic_array_test',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-5%',
            'rules' => [
                'factory_keys' => ['min_total'],
                'context' => ['amount' => 50],
            ],
        ]);

        $array = $condition->toConditionArray();

        expect($array['rules'])->toBeArray();
        expect($array['rules']['factory_keys'])->toBe(['min_total']);
        expect($array['rules']['context'])->toBe(['amount' => 50]);
    });
});

describe('Create Cart Condition', function (): void {
    it('creates CartCondition instance from model', function (): void {
        $condition = Condition::create([
            'name' => 'create_cart_condition',
            'display_name' => 'Created Condition',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
            'order' => 3,
            'attributes' => ['source' => 'test'],
        ]);

        $cartCondition = $condition->createCondition();

        expect($cartCondition)->toBeInstanceOf(CartCondition::class);
        expect($cartCondition->getName())->toBe('Created Condition');
        expect($cartCondition->getType())->toBe('discount');
        expect($cartCondition->getValue())->toBe('-10%');
        expect($cartCondition->getOrder())->toBe(3);
    });

    it('creates CartCondition with custom name', function (): void {
        $condition = Condition::create([
            'name' => 'custom_cart_condition',
            'display_name' => 'Original',
            'type' => 'tax',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '8%',
            'order' => 0,
        ]);

        $cartCondition = $condition->createCondition('Custom Name');

        expect($cartCondition->getName())->toBe('Custom Name');
    });
});

describe('Model Scopes', function (): void {
    beforeEach(function (): void {
        // Create test conditions
        Condition::create([
            'name' => 'active_discount',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
            'is_active' => true,
            'is_global' => false,
        ]);

        Condition::create([
            'name' => 'inactive_tax',
            'type' => 'tax',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '10%',
            'is_active' => false,
            'is_global' => false,
        ]);

        Condition::create([
            'name' => 'global_fee',
            'type' => 'fee',
            'target' => 'cart@grand_total/aggregate',
            'value' => '+500',
            'is_active' => true,
            'is_global' => true,
        ]);

        Condition::create([
            'name' => 'percentage_discount',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-20%',
            'is_active' => true,
        ]);
    });

    it('filters active conditions', function (): void {
        $active = Condition::query()->active()->get();

        expect($active->count())->toBe(3);
        expect($active->pluck('name')->toArray())->not->toContain('inactive_tax');
    });

    it('filters by type', function (): void {
        $discounts = Condition::query()->ofType('discount')->get();

        expect($discounts->count())->toBe(2);
        expect($discounts->pluck('type')->unique()->toArray())->toBe(['discount']);
    });

    it('filters discounts', function (): void {
        $discounts = Condition::query()->discounts()->get();

        expect($discounts->every(fn ($c) => $c->is_discount))->toBeTrue();
    });

    it('filters charges', function (): void {
        $charges = Condition::query()->charges()->get();

        expect($charges->every(fn ($c) => $c->is_charge))->toBeTrue();
    });

    it('filters global conditions', function (): void {
        $globals = Condition::query()->global()->get();

        expect($globals->count())->toBe(1);
        expect($globals->first()->name)->toBe('global_fee');
    });

    it('filters percentage-based conditions', function (): void {
        $percentages = Condition::query()->percentageBased()->get();

        expect($percentages->every(fn ($c) => $c->is_percentage))->toBeTrue();
    });

    it('filters for items scope by item target DSL conditions', function (): void {
        $itemCondition = Condition::factory()->forItems()->create([
            'name' => 'item-scope-condition',
            'is_active' => true,
        ]);

        Condition::factory()->create([
            'name' => 'cart-scope-condition',
            'target' => 'cart@cart_subtotal/aggregate',
            'is_active' => true,
        ]);

        $conditions = Condition::query()->forItems()->get();

        expect($conditions->pluck('id')->all())->toContain($itemCondition->id);
        expect($conditions->every(fn (Condition $condition): bool => str_starts_with($condition->target, 'items@')))->toBeTrue();
    });
});

describe('Factory', function (): void {
    it('can create condition using factory', function (): void {
        $condition = Condition::factory()->create();

        expect($condition)->toBeInstanceOf(Condition::class);
        expect($condition->exists)->toBeTrue();
    });

    it('can create discount using factory', function (): void {
        $condition = Condition::factory()->discount()->create();

        expect($condition->type)->toBe('discount');
        expect($condition->isDiscount())->toBeTrue();
    });

    it('can create tax using factory', function (): void {
        $condition = Condition::factory()->tax()->create();

        expect($condition->type)->toBe('tax');
        expect($condition->isTax())->toBeTrue();
    });

    it('can create fee using factory', function (): void {
        $condition = Condition::factory()->fee()->create();

        expect($condition->type)->toBe('fee');
        expect($condition->isFee())->toBeTrue();
    });

    it('can create shipping using factory', function (): void {
        $condition = Condition::factory()->shipping()->create();

        expect($condition->type)->toBe('shipping');
        expect($condition->isShipping())->toBeTrue();
    });

    it('can create active/inactive using factory', function (): void {
        $active = Condition::factory()->active()->create();
        $inactive = Condition::factory()->inactive()->create();

        expect($active->is_active)->toBeTrue();
        expect($inactive->is_active)->toBeFalse();
    });

    it('can create for items using factory', function (): void {
        $condition = Condition::factory()->forItems()->create();

        expect($condition->target)->toBe('items@item_discount/per-item');
    });

    it('can create with attributes using factory', function (): void {
        $condition = Condition::factory()->withAttributes([
            'custom_key' => 'custom_value',
        ])->create();

        expect($condition->attributes['custom_key'])->toBe('custom_value');
    });
});

describe('Build Rule Callables', function (): void {
    it('returns null when no factory keys', function (): void {
        $condition = Condition::create([
            'name' => 'no_rules',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
        ]);

        // Use reflection to call protected method
        $reflection = new ReflectionClass($condition);
        $method = $reflection->getMethod('buildRuleCallables');

        $result = $method->invoke($condition);

        expect($result)->toBeNull();
    });

    it('throws exception for unsupported factory key', function (): void {
        // Mock the RulesFactoryInterface
        $mockFactory = Mockery::mock(RulesFactoryInterface::class);
        $mockFactory->shouldReceive('canCreateRules')
            ->with('unsupported_key')
            ->andReturn(false);

        app()->instance(RulesFactoryInterface::class, $mockFactory);

        $condition = Condition::create([
            'name' => 'unsupported_rules',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
            'rules' => [
                'factory_keys' => ['unsupported_key'],
            ],
        ]);

        $reflection = new ReflectionClass($condition);
        $method = $reflection->getMethod('buildRuleCallables');

        expect(fn () => $method->invoke($condition))
            ->toThrow(InvalidArgumentException::class, 'Unsupported rule factory key [unsupported_key]');
    });

    it('builds rule callables from factory', function (): void {
        $ruleCallable = fn () => true;

        $mockFactory = Mockery::mock(RulesFactoryInterface::class);
        $mockFactory->shouldReceive('canCreateRules')
            ->with('test_key')
            ->andReturn(true);
        $mockFactory->shouldReceive('createRules')
            ->with('test_key', ['context' => ['param' => 'value']])
            ->andReturn([$ruleCallable]);

        app()->instance(RulesFactoryInterface::class, $mockFactory);

        $condition = Condition::create([
            'name' => 'with_rules',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
            'rules' => [
                'factory_keys' => ['test_key'],
                'context' => ['param' => 'value'],
            ],
        ]);

        $reflection = new ReflectionClass($condition);
        $method = $reflection->getMethod('buildRuleCallables');

        $result = $method->invoke($condition);

        expect($result)->toBeArray();
        expect($result)->toHaveCount(1);
        expect($result[0])->toBe($ruleCallable);
    });
});

describe('Casts', function (): void {
    it('casts attributes correctly', function (): void {
        $condition = Condition::create([
            'name' => 'cast_test',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'value' => '-10%',
            'order' => 5,
            'attributes' => ['key' => 'value'],
            'is_active' => true,
            'is_global' => false,
        ]);

        expect($condition->order)->toBeInt();
        expect($condition->attributes)->toBeArray();
        expect($condition->is_active)->toBeBool();
        expect($condition->is_global)->toBeBool();
        expect($condition->is_charge)->toBeBool();
        expect($condition->is_discount)->toBeBool();
        expect($condition->is_percentage)->toBeBool();
        expect($condition->is_dynamic)->toBeBool();
    });
});
