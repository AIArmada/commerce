<?php

declare(strict_types=1);

use AIArmada\Cart\AI\OptimizationResult;

describe('OptimizationResult', function (): void {
    it('can be instantiated', function (): void {
        $optimizedAt = new DateTimeImmutable();
        $improvements = [
            ['strategy' => 'email', 'action' => 'adjust_delay', 'reason' => 'Low conversion rate'],
        ];

        $result = new OptimizationResult(
            strategiesAnalyzed: 5,
            improvementsApplied: $improvements,
            optimizedAt: $optimizedAt
        );

        expect($result->strategiesAnalyzed)->toBe(5)
            ->and($result->improvementsApplied)->toBe($improvements)
            ->and($result->optimizedAt)->toBe($optimizedAt);
    });

    it('detects when improvements were made', function (): void {
        $result = new OptimizationResult(
            strategiesAnalyzed: 3,
            improvementsApplied: [
                ['strategy' => 'email', 'action' => 'adjust_delay', 'reason' => 'Low conversion'],
            ],
            optimizedAt: new DateTimeImmutable()
        );

        expect($result->hasImprovements())->toBeTrue();
    });

    it('detects when no improvements were made', function (): void {
        $result = new OptimizationResult(
            strategiesAnalyzed: 3,
            improvementsApplied: [],
            optimizedAt: new DateTimeImmutable()
        );

        expect($result->hasImprovements())->toBeFalse();
    });

    it('counts improvements correctly', function (): void {
        $result = new OptimizationResult(
            strategiesAnalyzed: 5,
            improvementsApplied: [
                ['strategy' => 'email', 'action' => 'adjust_delay', 'reason' => 'Low conversion'],
                ['strategy' => 'discount', 'action' => 'increase_amount', 'reason' => 'Better results'],
                ['strategy' => 'sms', 'action' => 'change_timing', 'reason' => 'Off-peak performance'],
            ],
            optimizedAt: new DateTimeImmutable()
        );

        expect($result->getImprovementCount())->toBe(3);
    });

    it('returns zero for improvement count when empty', function (): void {
        $result = new OptimizationResult(
            strategiesAnalyzed: 0,
            improvementsApplied: [],
            optimizedAt: new DateTimeImmutable()
        );

        expect($result->getImprovementCount())->toBe(0);
    });

    it('converts to array correctly', function (): void {
        $optimizedAt = new DateTimeImmutable('2024-01-15 14:30:00');
        $improvements = [
            ['strategy' => 'email', 'action' => 'adjust_delay', 'reason' => 'Low conversion'],
        ];

        $result = new OptimizationResult(
            strategiesAnalyzed: 5,
            improvementsApplied: $improvements,
            optimizedAt: $optimizedAt
        );

        $array = $result->toArray();

        expect($array)->toBeArray()
            ->and($array['strategies_analyzed'])->toBe(5)
            ->and($array['improvements_applied'])->toBe($improvements)
            ->and($array['improvement_count'])->toBe(1)
            ->and($array['optimized_at'])->toBe('2024-01-15 14:30:00');
    });
});
