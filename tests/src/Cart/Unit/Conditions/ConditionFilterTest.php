<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\ConditionFilter;
use AIArmada\Cart\Conditions\Enums\ConditionFilterOperator;

describe('ConditionFilter', function (): void {
    it('can be instantiated with valid parameters', function (): void {
        $filter = new ConditionFilter(
            field: 'category',
            operator: ConditionFilterOperator::EQ,
            value: 'electronics'
        );

        expect($filter->field)->toBe('category')
            ->and($filter->operator)->toBe(ConditionFilterOperator::EQ)
            ->and($filter->value)->toBe('electronics');
    });

    it('throws exception for empty field', function (): void {
        expect(fn () => new ConditionFilter(
            field: '',
            operator: ConditionFilterOperator::EQ,
            value: 'test'
        ))->toThrow(InvalidArgumentException::class, 'Filter field cannot be empty.');
    });

    it('throws exception for whitespace-only field', function (): void {
        expect(fn () => new ConditionFilter(
            field: '   ',
            operator: ConditionFilterOperator::EQ,
            value: 'test'
        ))->toThrow(InvalidArgumentException::class, 'Filter field cannot be empty.');
    });

    it('throws exception when array operator gets non-array value', function (): void {
        expect(fn () => new ConditionFilter(
            field: 'category',
            operator: ConditionFilterOperator::IN,
            value: 'not-an-array'
        ))->toThrow(InvalidArgumentException::class);
    });

    it('creates from array', function (): void {
        $filter = ConditionFilter::fromArray([
            'field' => 'price',
            'operator' => '>=',
            'value' => 100,
        ]);

        expect($filter->field)->toBe('price')
            ->and($filter->operator)->toBe(ConditionFilterOperator::GTE)
            ->and($filter->value)->toBe(100);
    });

    it('throws exception when field missing in array', function (): void {
        expect(fn () => ConditionFilter::fromArray([
            'operator' => 'eq',
            'value' => 'test',
        ]))->toThrow(InvalidArgumentException::class, 'Filter field is required.');
    });

    it('throws exception when operator missing in array', function (): void {
        expect(fn () => ConditionFilter::fromArray([
            'field' => 'category',
            'value' => 'test',
        ]))->toThrow(InvalidArgumentException::class, 'Filter operator is required.');
    });

    it('converts to array', function (): void {
        $filter = new ConditionFilter(
            field: 'brand',
            operator: ConditionFilterOperator::CONTAINS,
            value: 'Nike'
        );

        $array = $filter->toArray();

        expect($array)->toBe([
            'field' => 'brand',
            'operator' => '~',
            'value' => 'Nike',
        ]);
    });

    it('serializes to JSON', function (): void {
        $filter = new ConditionFilter(
            field: 'quantity',
            operator: ConditionFilterOperator::GT,
            value: 5
        );

        $json = json_encode($filter);
        $decoded = json_decode($json, true);

        expect($decoded)->toBe([
            'field' => 'quantity',
            'operator' => '>',
            'value' => 5,
        ]);
    });

    it('generates DSL token for simple string value', function (): void {
        $filter = new ConditionFilter(
            field: 'category',
            operator: ConditionFilterOperator::EQ,
            value: 'electronics'
        );

        expect($filter->toDslToken())->toBe('category=electronics');
    });

    it('generates DSL token for numeric value', function (): void {
        $filter = new ConditionFilter(
            field: 'price',
            operator: ConditionFilterOperator::GTE,
            value: 100
        );

        expect($filter->toDslToken())->toBe('price>=100');
    });

    it('generates DSL token for boolean value', function (): void {
        $filter = new ConditionFilter(
            field: 'is_active',
            operator: ConditionFilterOperator::EQ,
            value: true
        );

        expect($filter->toDslToken())->toBe('is_active=true');
    });

    it('generates DSL token for false boolean value', function (): void {
        $filter = new ConditionFilter(
            field: 'is_deleted',
            operator: ConditionFilterOperator::EQ,
            value: false
        );

        expect($filter->toDslToken())->toBe('is_deleted=false');
    });

    it('generates DSL token for null value', function (): void {
        $filter = new ConditionFilter(
            field: 'category',
            operator: ConditionFilterOperator::EQ,
            value: null
        );

        expect($filter->toDslToken())->toBe('category=null');
    });

    it('generates DSL token for array value', function (): void {
        $filter = new ConditionFilter(
            field: 'category',
            operator: ConditionFilterOperator::IN,
            value: ['electronics', 'clothing']
        );

        expect($filter->toDslToken())->toBe('categoryin[electronics,clothing]');
    });

    it('generates DSL token for string with special characters', function (): void {
        $filter = new ConditionFilter(
            field: 'name',
            operator: ConditionFilterOperator::EQ,
            value: 'Hello World!'
        );

        expect($filter->toDslToken())->toBe('name="Hello World!"');
    });

    it('generates DSL token for empty string value', function (): void {
        $filter = new ConditionFilter(
            field: 'description',
            operator: ConditionFilterOperator::EQ,
            value: ''
        );

        expect($filter->toDslToken())->toBe("description=''");
    });

    it('escapes quotes in string values', function (): void {
        $filter = new ConditionFilter(
            field: 'name',
            operator: ConditionFilterOperator::EQ,
            value: 'He said "hello"'
        );

        expect($filter->toDslToken())->toBe('name="He said \"hello\""');
    });

    it('handles not equals operator', function (): void {
        $filter = new ConditionFilter(
            field: 'status',
            operator: ConditionFilterOperator::NEQ,
            value: 'cancelled'
        );

        $dsl = $filter->toDslToken();

        expect($dsl)->toContain('status')
            ->and($dsl)->toContain('cancelled');
    });

    it('handles less than operator', function (): void {
        $filter = new ConditionFilter(
            field: 'quantity',
            operator: ConditionFilterOperator::LT,
            value: 10
        );

        expect($filter->toDslToken())->toBe('quantity<10');
    });

    it('handles less than or equal operator', function (): void {
        $filter = new ConditionFilter(
            field: 'quantity',
            operator: ConditionFilterOperator::LTE,
            value: 10
        );

        expect($filter->toDslToken())->toBe('quantity<=10');
    });

    it('handles greater than operator', function (): void {
        $filter = new ConditionFilter(
            field: 'price',
            operator: ConditionFilterOperator::GT,
            value: 50
        );

        expect($filter->toDslToken())->toBe('price>50');
    });

    it('handles not in operator', function (): void {
        $filter = new ConditionFilter(
            field: 'status',
            operator: ConditionFilterOperator::NOT_IN,
            value: ['cancelled', 'refunded']
        );

        $dsl = $filter->toDslToken();

        expect($dsl)->toContain('status')
            ->and($dsl)->toContain('[cancelled,refunded]');
    });

    it('handles starts with operator', function (): void {
        $filter = new ConditionFilter(
            field: 'sku',
            operator: ConditionFilterOperator::STARTS_WITH,
            value: 'PRO-'
        );

        $dsl = $filter->toDslToken();

        expect($dsl)->toContain('sku')
            ->and($dsl)->toContain('PRO-');
    });

    it('handles ends with operator', function (): void {
        $filter = new ConditionFilter(
            field: 'email',
            operator: ConditionFilterOperator::ENDS_WITH,
            value: '@example.com'
        );

        $dsl = $filter->toDslToken();

        expect($dsl)->toContain('email')
            ->and($dsl)->toContain('@example.com');
    });
});
