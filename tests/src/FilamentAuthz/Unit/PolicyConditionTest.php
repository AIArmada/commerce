<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\ConditionOperator;
use AIArmada\FilamentAuthz\ValueObjects\PolicyCondition;

describe('PolicyCondition', function (): void {
    describe('constructor', function (): void {
        it('creates a policy condition with all parameters', function (): void {
            $condition = new PolicyCondition(
                attribute: 'user.role',
                operator: ConditionOperator::Equals,
                value: 'admin',
                description: 'User must be admin'
            );

            expect($condition->attribute)->toBe('user.role')
                ->and($condition->operator)->toBe(ConditionOperator::Equals)
                ->and($condition->value)->toBe('admin')
                ->and($condition->description)->toBe('User must be admin');
        });

        it('creates a policy condition without description', function (): void {
            $condition = new PolicyCondition(
                attribute: 'status',
                operator: ConditionOperator::In,
                value: ['active', 'pending']
            );

            expect($condition->description)->toBeNull();
        });
    });

    describe('fromArray', function (): void {
        it('creates condition from array with all fields', function (): void {
            $condition = PolicyCondition::fromArray([
                'attribute' => 'user.age',
                'operator' => 'gt',
                'value' => 18,
                'description' => 'User must be adult',
            ]);

            expect($condition->attribute)->toBe('user.age')
                ->and($condition->operator)->toBe(ConditionOperator::GreaterThan)
                ->and($condition->value)->toBe(18)
                ->and($condition->description)->toBe('User must be adult');
        });

        it('creates condition from array without description', function (): void {
            $condition = PolicyCondition::fromArray([
                'attribute' => 'status',
                'operator' => 'eq',
                'value' => 'active',
            ]);

            expect($condition->description)->toBeNull();
        });
    });

    describe('static factory methods', function (): void {
        it('creates equals condition', function (): void {
            $condition = PolicyCondition::equals('status', 'active');

            expect($condition->attribute)->toBe('status')
                ->and($condition->operator)->toBe(ConditionOperator::Equals)
                ->and($condition->value)->toBe('active');
        });

        it('creates notEquals condition', function (): void {
            $condition = PolicyCondition::notEquals('status', 'deleted');

            expect($condition->attribute)->toBe('status')
                ->and($condition->operator)->toBe(ConditionOperator::NotEquals)
                ->and($condition->value)->toBe('deleted');
        });

        it('creates greaterThan condition', function (): void {
            $condition = PolicyCondition::greaterThan('age', 18);

            expect($condition->attribute)->toBe('age')
                ->and($condition->operator)->toBe(ConditionOperator::GreaterThan)
                ->and($condition->value)->toBe(18);
        });

        it('creates lessThan condition', function (): void {
            $condition = PolicyCondition::lessThan('price', 100);

            expect($condition->attribute)->toBe('price')
                ->and($condition->operator)->toBe(ConditionOperator::LessThan)
                ->and($condition->value)->toBe(100);
        });

        it('creates in condition', function (): void {
            $condition = PolicyCondition::in('status', ['active', 'pending']);

            expect($condition->attribute)->toBe('status')
                ->and($condition->operator)->toBe(ConditionOperator::In)
                ->and($condition->value)->toBe(['active', 'pending']);
        });

        it('creates notIn condition', function (): void {
            $condition = PolicyCondition::notIn('role', ['banned', 'suspended']);

            expect($condition->attribute)->toBe('role')
                ->and($condition->operator)->toBe(ConditionOperator::NotIn)
                ->and($condition->value)->toBe(['banned', 'suspended']);
        });

        it('creates contains condition', function (): void {
            $condition = PolicyCondition::contains('email', '@example.com');

            expect($condition->attribute)->toBe('email')
                ->and($condition->operator)->toBe(ConditionOperator::Contains)
                ->and($condition->value)->toBe('@example.com');
        });

        it('creates startsWith condition', function (): void {
            $condition = PolicyCondition::startsWith('name', 'Dr.');

            expect($condition->attribute)->toBe('name')
                ->and($condition->operator)->toBe(ConditionOperator::StartsWith)
                ->and($condition->value)->toBe('Dr.');
        });

        it('creates between condition', function (): void {
            $condition = PolicyCondition::between('age', [18, 65]);

            expect($condition->attribute)->toBe('age')
                ->and($condition->operator)->toBe(ConditionOperator::Between)
                ->and($condition->value)->toBe([18, 65]);
        });

        it('creates isNull condition', function (): void {
            $condition = PolicyCondition::isNull('deleted_at');

            expect($condition->attribute)->toBe('deleted_at')
                ->and($condition->operator)->toBe(ConditionOperator::IsNull)
                ->and($condition->value)->toBeNull();
        });

        it('creates isNotNull condition', function (): void {
            $condition = PolicyCondition::isNotNull('verified_at');

            expect($condition->attribute)->toBe('verified_at')
                ->and($condition->operator)->toBe(ConditionOperator::IsNotNull)
                ->and($condition->value)->toBeNull();
        });

        it('creates matches condition', function (): void {
            $condition = PolicyCondition::matches('email', '/^[a-z]+@/');

            expect($condition->attribute)->toBe('email')
                ->and($condition->operator)->toBe(ConditionOperator::Matches)
                ->and($condition->value)->toBe('/^[a-z]+@/');
        });
    });

    describe('toArray', function (): void {
        it('converts to array with description', function (): void {
            $condition = new PolicyCondition(
                attribute: 'user.role',
                operator: ConditionOperator::Equals,
                value: 'admin',
                description: 'Must be admin'
            );

            $array = $condition->toArray();

            expect($array)->toBe([
                'attribute' => 'user.role',
                'operator' => 'eq',
                'value' => 'admin',
                'description' => 'Must be admin',
            ]);
        });

        it('converts to array without description', function (): void {
            $condition = PolicyCondition::in('status', ['a', 'b']);

            $array = $condition->toArray();

            expect($array['description'])->toBeNull();
        });
    });

    describe('evaluate', function (): void {
        it('evaluates equals condition against context', function (): void {
            $condition = PolicyCondition::equals('status', 'active');

            expect($condition->evaluate(['status' => 'active']))->toBeTrue()
                ->and($condition->evaluate(['status' => 'inactive']))->toBeFalse();
        });

        it('evaluates with nested attribute using dot notation', function (): void {
            $condition = PolicyCondition::equals('user.role', 'admin');

            expect($condition->evaluate(['user' => ['role' => 'admin']]))->toBeTrue()
                ->and($condition->evaluate(['user' => ['role' => 'guest']]))->toBeFalse();
        });

        it('evaluates greaterThan condition', function (): void {
            $condition = PolicyCondition::greaterThan('age', 18);

            expect($condition->evaluate(['age' => 25]))->toBeTrue()
                ->and($condition->evaluate(['age' => 18]))->toBeFalse()
                ->and($condition->evaluate(['age' => 15]))->toBeFalse();
        });

        it('evaluates in condition', function (): void {
            $condition = PolicyCondition::in('status', ['active', 'pending']);

            expect($condition->evaluate(['status' => 'active']))->toBeTrue()
                ->and($condition->evaluate(['status' => 'pending']))->toBeTrue()
                ->and($condition->evaluate(['status' => 'deleted']))->toBeFalse();
        });

        it('evaluates isNull condition', function (): void {
            $condition = PolicyCondition::isNull('deleted_at');

            expect($condition->evaluate(['deleted_at' => null]))->toBeTrue()
                ->and($condition->evaluate(['deleted_at' => '2024-01-01']))->toBeFalse();
        });

        it('evaluates between condition', function (): void {
            $condition = PolicyCondition::between('score', [60, 100]);

            expect($condition->evaluate(['score' => 75]))->toBeTrue()
                ->and($condition->evaluate(['score' => 60]))->toBeTrue()
                ->and($condition->evaluate(['score' => 100]))->toBeTrue()
                ->and($condition->evaluate(['score' => 50]))->toBeFalse();
        });

        it('handles missing attribute in context', function (): void {
            $condition = PolicyCondition::equals('missing', 'value');

            expect($condition->evaluate([]))->toBeFalse();
        });
    });

    describe('describe', function (): void {
        it('returns custom description when provided', function (): void {
            $condition = new PolicyCondition(
                attribute: 'status',
                operator: ConditionOperator::Equals,
                value: 'active',
                description: 'Status must be active'
            );

            expect($condition->describe())->toBe('Status must be active');
        });

        it('generates description for simple value', function (): void {
            $condition = PolicyCondition::equals('status', 'active');

            expect($condition->describe())->toContain('status')
                ->and($condition->describe())->toContain('active');
        });

        it('generates description for array value', function (): void {
            $condition = PolicyCondition::in('status', ['a', 'b', 'c']);

            expect($condition->describe())->toContain('status')
                ->and($condition->describe())->toContain('[a, b, c]');
        });

        it('generates description for null value', function (): void {
            $condition = PolicyCondition::isNull('deleted_at');

            $description = $condition->describe();

            expect($description)->toContain('deleted_at');
        });
    });
});
