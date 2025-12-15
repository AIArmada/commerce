<?php

declare(strict_types=1);

use AIArmada\Vouchers\Targeting\Enums\TargetingMode;

describe('TargetingMode Enum', function (): void {
    describe('values', function (): void {
        it('has All mode', function (): void {
            expect(TargetingMode::All->value)->toBe('all');
        });

        it('has Any mode', function (): void {
            expect(TargetingMode::Any->value)->toBe('any');
        });

        it('has Custom mode', function (): void {
            expect(TargetingMode::Custom->value)->toBe('custom');
        });
    });

    describe('label', function (): void {
        it('returns label for All mode', function (): void {
            expect(TargetingMode::All->label())->toBe('All Rules Must Match');
        });

        it('returns label for Any mode', function (): void {
            expect(TargetingMode::Any->label())->toBe('Any Rule Must Match');
        });

        it('returns label for Custom mode', function (): void {
            expect(TargetingMode::Custom->label())->toBe('Custom Expression');
        });
    });

    describe('description', function (): void {
        it('returns description for All mode', function (): void {
            $description = TargetingMode::All->description();
            expect($description)->toContain('ALL targeting rules')
                ->toContain('AND logic');
        });

        it('returns description for Any mode', function (): void {
            $description = TargetingMode::Any->description();
            expect($description)->toContain('ANY targeting rule')
                ->toContain('OR logic');
        });

        it('returns description for Custom mode', function (): void {
            $description = TargetingMode::Custom->description();
            expect($description)->toContain('custom boolean expression')
                ->toContain('AND, OR, NOT');
        });
    });

    describe('from', function (): void {
        it('creates from string value', function (): void {
            expect(TargetingMode::from('all'))->toBe(TargetingMode::All);
            expect(TargetingMode::from('any'))->toBe(TargetingMode::Any);
            expect(TargetingMode::from('custom'))->toBe(TargetingMode::Custom);
        });

        it('throws for invalid value', function (): void {
            expect(fn () => TargetingMode::from('invalid'))
                ->toThrow(ValueError::class);
        });
    });

    describe('tryFrom', function (): void {
        it('returns null for invalid value', function (): void {
            expect(TargetingMode::tryFrom('invalid'))->toBeNull();
        });

        it('returns enum for valid value', function (): void {
            expect(TargetingMode::tryFrom('all'))->toBe(TargetingMode::All);
        });
    });
});
