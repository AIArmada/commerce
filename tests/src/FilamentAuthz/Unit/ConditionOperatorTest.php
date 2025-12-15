<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\ConditionOperator;

// Label tests
test('all operators have labels', function (): void {
    foreach (ConditionOperator::cases() as $operator) {
        expect($operator->label())->toBeString()->not->toBeEmpty();
    }
});

// Symbol tests
test('all operators have symbols', function (): void {
    foreach (ConditionOperator::cases() as $operator) {
        expect($operator->symbol())->toBeString()->not->toBeEmpty();
    }
});

// RequiresValue tests
test('IsNull does not require value', function (): void {
    expect(ConditionOperator::IsNull->requiresValue())->toBeFalse();
});

test('IsNotNull does not require value', function (): void {
    expect(ConditionOperator::IsNotNull->requiresValue())->toBeFalse();
});

test('IsTrue does not require value', function (): void {
    expect(ConditionOperator::IsTrue->requiresValue())->toBeFalse();
});

test('IsFalse does not require value', function (): void {
    expect(ConditionOperator::IsFalse->requiresValue())->toBeFalse();
});

test('Equals requires value', function (): void {
    expect(ConditionOperator::Equals->requiresValue())->toBeTrue();
});

test('Contains requires value', function (): void {
    expect(ConditionOperator::Contains->requiresValue())->toBeTrue();
});

// isArrayOperator tests
test('In is array operator', function (): void {
    expect(ConditionOperator::In->isArrayOperator())->toBeTrue();
});

test('NotIn is array operator', function (): void {
    expect(ConditionOperator::NotIn->isArrayOperator())->toBeTrue();
});

test('ContainsAny is array operator', function (): void {
    expect(ConditionOperator::ContainsAny->isArrayOperator())->toBeTrue();
});

test('ContainsAll is array operator', function (): void {
    expect(ConditionOperator::ContainsAll->isArrayOperator())->toBeTrue();
});

test('Between is array operator', function (): void {
    expect(ConditionOperator::Between->isArrayOperator())->toBeTrue();
});

test('Equals is not array operator', function (): void {
    expect(ConditionOperator::Equals->isArrayOperator())->toBeFalse();
});

// isStringOperator tests
test('Contains is string operator', function (): void {
    expect(ConditionOperator::Contains->isStringOperator())->toBeTrue();
});

test('NotContains is string operator', function (): void {
    expect(ConditionOperator::NotContains->isStringOperator())->toBeTrue();
});

test('StartsWith is string operator', function (): void {
    expect(ConditionOperator::StartsWith->isStringOperator())->toBeTrue();
});

test('EndsWith is string operator', function (): void {
    expect(ConditionOperator::EndsWith->isStringOperator())->toBeTrue();
});

test('Matches is string operator', function (): void {
    expect(ConditionOperator::Matches->isStringOperator())->toBeTrue();
});

test('Equals is not string operator', function (): void {
    expect(ConditionOperator::Equals->isStringOperator())->toBeFalse();
});

// isDateOperator tests
test('Before is date operator', function (): void {
    expect(ConditionOperator::Before->isDateOperator())->toBeTrue();
});

test('After is date operator', function (): void {
    expect(ConditionOperator::After->isDateOperator())->toBeTrue();
});

test('Between is date operator', function (): void {
    expect(ConditionOperator::Between->isDateOperator())->toBeTrue();
});

test('Equals is not date operator', function (): void {
    expect(ConditionOperator::Equals->isDateOperator())->toBeFalse();
});

// isComparisonOperator tests
test('Equals is comparison operator', function (): void {
    expect(ConditionOperator::Equals->isComparisonOperator())->toBeTrue();
});

test('NotEquals is comparison operator', function (): void {
    expect(ConditionOperator::NotEquals->isComparisonOperator())->toBeTrue();
});

test('GreaterThan is comparison operator', function (): void {
    expect(ConditionOperator::GreaterThan->isComparisonOperator())->toBeTrue();
});

test('GreaterThanOrEquals is comparison operator', function (): void {
    expect(ConditionOperator::GreaterThanOrEquals->isComparisonOperator())->toBeTrue();
});

test('LessThan is comparison operator', function (): void {
    expect(ConditionOperator::LessThan->isComparisonOperator())->toBeTrue();
});

test('LessThanOrEquals is comparison operator', function (): void {
    expect(ConditionOperator::LessThanOrEquals->isComparisonOperator())->toBeTrue();
});

test('Contains is not comparison operator', function (): void {
    expect(ConditionOperator::Contains->isComparisonOperator())->toBeFalse();
});

// Evaluate tests - Equality
test('evaluate Equals returns true for equal values', function (): void {
    expect(ConditionOperator::Equals->evaluate('admin', 'admin'))->toBeTrue();
});

test('evaluate Equals returns false for different values', function (): void {
    expect(ConditionOperator::Equals->evaluate('admin', 'user'))->toBeFalse();
});

test('evaluate NotEquals returns true for different values', function (): void {
    expect(ConditionOperator::NotEquals->evaluate('admin', 'user'))->toBeTrue();
});

test('evaluate NotEquals returns false for equal values', function (): void {
    expect(ConditionOperator::NotEquals->evaluate('admin', 'admin'))->toBeFalse();
});

// Evaluate tests - Comparison
test('evaluate GreaterThan returns true when greater', function (): void {
    expect(ConditionOperator::GreaterThan->evaluate(10, 5))->toBeTrue();
});

test('evaluate GreaterThan returns false when equal', function (): void {
    expect(ConditionOperator::GreaterThan->evaluate(5, 5))->toBeFalse();
});

test('evaluate GreaterThan returns false when less', function (): void {
    expect(ConditionOperator::GreaterThan->evaluate(3, 5))->toBeFalse();
});

test('evaluate GreaterThanOrEquals returns true when greater', function (): void {
    expect(ConditionOperator::GreaterThanOrEquals->evaluate(10, 5))->toBeTrue();
});

test('evaluate GreaterThanOrEquals returns true when equal', function (): void {
    expect(ConditionOperator::GreaterThanOrEquals->evaluate(5, 5))->toBeTrue();
});

test('evaluate GreaterThanOrEquals returns false when less', function (): void {
    expect(ConditionOperator::GreaterThanOrEquals->evaluate(3, 5))->toBeFalse();
});

test('evaluate LessThan returns true when less', function (): void {
    expect(ConditionOperator::LessThan->evaluate(3, 5))->toBeTrue();
});

test('evaluate LessThan returns false when equal', function (): void {
    expect(ConditionOperator::LessThan->evaluate(5, 5))->toBeFalse();
});

test('evaluate LessThan returns false when greater', function (): void {
    expect(ConditionOperator::LessThan->evaluate(10, 5))->toBeFalse();
});

test('evaluate LessThanOrEquals returns true when less', function (): void {
    expect(ConditionOperator::LessThanOrEquals->evaluate(3, 5))->toBeTrue();
});

test('evaluate LessThanOrEquals returns true when equal', function (): void {
    expect(ConditionOperator::LessThanOrEquals->evaluate(5, 5))->toBeTrue();
});

test('evaluate LessThanOrEquals returns false when greater', function (): void {
    expect(ConditionOperator::LessThanOrEquals->evaluate(10, 5))->toBeFalse();
});

// Evaluate tests - String operators
test('evaluate Contains returns true when contains', function (): void {
    expect(ConditionOperator::Contains->evaluate('hello world', 'world'))->toBeTrue();
});

test('evaluate Contains returns false when not contains', function (): void {
    expect(ConditionOperator::Contains->evaluate('hello world', 'foo'))->toBeFalse();
});

test('evaluate Contains returns false for non-string', function (): void {
    expect(ConditionOperator::Contains->evaluate(123, 'foo'))->toBeFalse();
});

test('evaluate NotContains returns true when not contains', function (): void {
    expect(ConditionOperator::NotContains->evaluate('hello world', 'foo'))->toBeTrue();
});

test('evaluate NotContains returns false when contains', function (): void {
    expect(ConditionOperator::NotContains->evaluate('hello world', 'world'))->toBeFalse();
});

test('evaluate NotContains returns false for non-string', function (): void {
    expect(ConditionOperator::NotContains->evaluate(123, 'foo'))->toBeFalse();
});

test('evaluate StartsWith returns true when starts with', function (): void {
    expect(ConditionOperator::StartsWith->evaluate('hello world', 'hello'))->toBeTrue();
});

test('evaluate StartsWith returns false when not starts with', function (): void {
    expect(ConditionOperator::StartsWith->evaluate('hello world', 'world'))->toBeFalse();
});

test('evaluate StartsWith returns false for non-string', function (): void {
    expect(ConditionOperator::StartsWith->evaluate(123, 'foo'))->toBeFalse();
});

test('evaluate EndsWith returns true when ends with', function (): void {
    expect(ConditionOperator::EndsWith->evaluate('hello world', 'world'))->toBeTrue();
});

test('evaluate EndsWith returns false when not ends with', function (): void {
    expect(ConditionOperator::EndsWith->evaluate('hello world', 'hello'))->toBeFalse();
});

test('evaluate EndsWith returns false for non-string', function (): void {
    expect(ConditionOperator::EndsWith->evaluate(123, 'foo'))->toBeFalse();
});

test('evaluate Matches returns true for regex match', function (): void {
    expect(ConditionOperator::Matches->evaluate('hello123', '/\d+/'))->toBeTrue();
});

test('evaluate Matches returns false for no regex match', function (): void {
    expect(ConditionOperator::Matches->evaluate('hello', '/\d+/'))->toBeFalse();
});

test('evaluate Matches returns false for non-string', function (): void {
    expect(ConditionOperator::Matches->evaluate(123, '/\d+/'))->toBeFalse();
});

// Evaluate tests - Collection operators
test('evaluate In returns true when in array', function (): void {
    expect(ConditionOperator::In->evaluate('admin', ['admin', 'user', 'guest']))->toBeTrue();
});

test('evaluate In returns false when not in array', function (): void {
    expect(ConditionOperator::In->evaluate('manager', ['admin', 'user', 'guest']))->toBeFalse();
});

test('evaluate In returns false for non-array condition', function (): void {
    expect(ConditionOperator::In->evaluate('admin', 'admin'))->toBeFalse();
});

test('evaluate NotIn returns true when not in array', function (): void {
    expect(ConditionOperator::NotIn->evaluate('manager', ['admin', 'user', 'guest']))->toBeTrue();
});

test('evaluate NotIn returns false when in array', function (): void {
    expect(ConditionOperator::NotIn->evaluate('admin', ['admin', 'user', 'guest']))->toBeFalse();
});

test('evaluate NotIn returns false for non-array condition', function (): void {
    expect(ConditionOperator::NotIn->evaluate('admin', 'admin'))->toBeFalse();
});

test('evaluate ContainsAny returns true when intersection exists', function (): void {
    expect(ConditionOperator::ContainsAny->evaluate(['admin', 'editor'], ['admin', 'user']))->toBeTrue();
});

test('evaluate ContainsAny returns false when no intersection', function (): void {
    expect(ConditionOperator::ContainsAny->evaluate(['manager', 'editor'], ['admin', 'user']))->toBeFalse();
});

test('evaluate ContainsAny returns false for non-array attribute', function (): void {
    expect(ConditionOperator::ContainsAny->evaluate('admin', ['admin', 'user']))->toBeFalse();
});

test('evaluate ContainsAny returns false for non-array condition', function (): void {
    expect(ConditionOperator::ContainsAny->evaluate(['admin', 'user'], 'admin'))->toBeFalse();
});

test('evaluate ContainsAll returns true when all present', function (): void {
    expect(ConditionOperator::ContainsAll->evaluate(['admin', 'editor', 'user'], ['admin', 'editor']))->toBeTrue();
});

test('evaluate ContainsAll returns false when not all present', function (): void {
    expect(ConditionOperator::ContainsAll->evaluate(['admin', 'editor'], ['admin', 'user']))->toBeFalse();
});

test('evaluate ContainsAll returns false for non-array attribute', function (): void {
    expect(ConditionOperator::ContainsAll->evaluate('admin', ['admin', 'user']))->toBeFalse();
});

test('evaluate ContainsAll returns false for non-array condition', function (): void {
    expect(ConditionOperator::ContainsAll->evaluate(['admin', 'user'], 'admin'))->toBeFalse();
});

// Evaluate tests - Null checks
test('evaluate IsNull returns true for null', function (): void {
    expect(ConditionOperator::IsNull->evaluate(null))->toBeTrue();
});

test('evaluate IsNull returns false for non-null', function (): void {
    expect(ConditionOperator::IsNull->evaluate('value'))->toBeFalse();
});

test('evaluate IsNotNull returns true for non-null', function (): void {
    expect(ConditionOperator::IsNotNull->evaluate('value'))->toBeTrue();
});

test('evaluate IsNotNull returns false for null', function (): void {
    expect(ConditionOperator::IsNotNull->evaluate(null))->toBeFalse();
});

// Evaluate tests - Boolean
test('evaluate IsTrue returns true for true', function (): void {
    expect(ConditionOperator::IsTrue->evaluate(true))->toBeTrue();
});

test('evaluate IsTrue returns false for false', function (): void {
    expect(ConditionOperator::IsTrue->evaluate(false))->toBeFalse();
});

test('evaluate IsTrue returns false for truthy non-boolean', function (): void {
    expect(ConditionOperator::IsTrue->evaluate(1))->toBeFalse();
});

test('evaluate IsFalse returns true for false', function (): void {
    expect(ConditionOperator::IsFalse->evaluate(false))->toBeTrue();
});

test('evaluate IsFalse returns false for true', function (): void {
    expect(ConditionOperator::IsFalse->evaluate(true))->toBeFalse();
});

test('evaluate IsFalse returns false for falsy non-boolean', function (): void {
    expect(ConditionOperator::IsFalse->evaluate(0))->toBeFalse();
});

// Evaluate tests - Date/Time
test('evaluate Before returns true when before', function (): void {
    expect(ConditionOperator::Before->evaluate('2024-01-01', '2024-06-01'))->toBeTrue();
});

test('evaluate Before returns false when after', function (): void {
    expect(ConditionOperator::Before->evaluate('2024-06-01', '2024-01-01'))->toBeFalse();
});

test('evaluate After returns true when after', function (): void {
    expect(ConditionOperator::After->evaluate('2024-06-01', '2024-01-01'))->toBeTrue();
});

test('evaluate After returns false when before', function (): void {
    expect(ConditionOperator::After->evaluate('2024-01-01', '2024-06-01'))->toBeFalse();
});

test('evaluate Between returns true when between', function (): void {
    expect(ConditionOperator::Between->evaluate(5, [1, 10]))->toBeTrue();
});

test('evaluate Between returns true for boundary values', function (): void {
    expect(ConditionOperator::Between->evaluate(1, [1, 10]))->toBeTrue();
    expect(ConditionOperator::Between->evaluate(10, [1, 10]))->toBeTrue();
});

test('evaluate Between returns false when outside', function (): void {
    expect(ConditionOperator::Between->evaluate(15, [1, 10]))->toBeFalse();
});

test('evaluate Between returns false for non-array condition', function (): void {
    expect(ConditionOperator::Between->evaluate(5, 10))->toBeFalse();
});

test('evaluate Between returns false for wrong array size', function (): void {
    expect(ConditionOperator::Between->evaluate(5, [1, 5, 10]))->toBeFalse();
});
