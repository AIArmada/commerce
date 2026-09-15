<?php

declare(strict_types=1);

use AIArmada\Affiliates\Services\CohortAnalyzer;

/**
 * CohortAnalyzer structure tests.
 *
 * Behavioral coverage lives in Services/CohortAnalyzerTest, which runs against SQLite.
 */
describe('CohortAnalyzer', function (): void {
    test('can be instantiated', function (): void {
        $analyzer = new CohortAnalyzer;

        expect($analyzer)->toBeInstanceOf(CohortAnalyzer::class);
    });

    test('analyzeMonthly method exists with correct signature', function (): void {
        $analyzer = new CohortAnalyzer;

        expect(method_exists($analyzer, 'analyzeMonthly'))->toBeTrue();

        $reflection = new ReflectionMethod(CohortAnalyzer::class, 'analyzeMonthly');

        // Check parameters
        $params = $reflection->getParameters();
        expect(count($params))->toBe(3);

        expect($params[0]->getName())->toBe('from');
        expect($params[0]->allowsNull())->toBeTrue();

        expect($params[1]->getName())->toBe('to');
        expect($params[1]->allowsNull())->toBeTrue();

        expect($params[2]->getName())->toBe('monthsToTrack');
        expect($params[2]->getDefaultValue())->toBe(12);

        // Check return type
        expect($reflection->getReturnType()->getName())->toBe('array');
    });

    test('calculateRetentionCurve method exists with correct signature', function (): void {
        $analyzer = new CohortAnalyzer;

        expect(method_exists($analyzer, 'calculateRetentionCurve'))->toBeTrue();

        $reflection = new ReflectionMethod(CohortAnalyzer::class, 'calculateRetentionCurve');

        $params = $reflection->getParameters();
        expect(count($params))->toBe(3);

        expect($params[2]->getName())->toBe('maxMonths');
        expect($params[2]->getDefaultValue())->toBe(12);

        expect($reflection->getReturnType()->getName())->toBe('array');
    });

    test('calculateLtv method exists with correct signature', function (): void {
        $analyzer = new CohortAnalyzer;

        expect(method_exists($analyzer, 'calculateLtv'))->toBeTrue();

        $reflection = new ReflectionMethod(CohortAnalyzer::class, 'calculateLtv');

        $params = $reflection->getParameters();
        expect(count($params))->toBe(2);

        expect($reflection->getReturnType()->getName())->toBe('array');
    });

    test('compareCohorts method exists with correct signature', function (): void {
        $analyzer = new CohortAnalyzer;

        expect(method_exists($analyzer, 'compareCohorts'))->toBeTrue();

        $reflection = new ReflectionMethod(CohortAnalyzer::class, 'compareCohorts');

        expect($reflection->getReturnType()->getName())->toBe('array');
    });

    test('analyzeBySource method exists with correct signature', function (): void {
        $analyzer = new CohortAnalyzer;

        expect(method_exists($analyzer, 'analyzeBySource'))->toBeTrue();

        $reflection = new ReflectionMethod(CohortAnalyzer::class, 'analyzeBySource');

        expect($reflection->getReturnType()->getName())->toBe('array');
    });

});

describe('CohortAnalyzer class structure', function (): void {
    test('is declared as final', function (): void {
        $reflection = new ReflectionClass(CohortAnalyzer::class);

        expect($reflection->isFinal())->toBeTrue();
    });

    test('has private getCohorts method', function (): void {
        $reflection = new ReflectionClass(CohortAnalyzer::class);

        expect($reflection->hasMethod('getCohorts'))->toBeTrue();
        expect($reflection->getMethod('getCohorts')->isPrivate())->toBeTrue();
    });

    test('has private calculateMonthlyBreakdown method', function (): void {
        $reflection = new ReflectionClass(CohortAnalyzer::class);

        expect($reflection->hasMethod('calculateMonthlyBreakdown'))->toBeTrue();
        expect($reflection->getMethod('calculateMonthlyBreakdown')->isPrivate())->toBeTrue();
    });
});
