<?php

declare(strict_types=1);

use AIArmada\Jnt\Support\TypeTransformer;

describe('TypeTransformer - Generic Methods', function (): void {
    it('converts integers to integer strings', function (): void {
        expect(TypeTransformer::toIntegerString(5))->toBe('5');
        expect(TypeTransformer::toIntegerString(999))->toBe('999');
        expect(TypeTransformer::toIntegerString(1))->toBe('1');
    });

    it('converts floats to integer strings (truncates decimals)', function (): void {
        expect(TypeTransformer::toIntegerString(5.7))->toBe('5');
        expect(TypeTransformer::toIntegerString(999.99))->toBe('999');
        expect(TypeTransformer::toIntegerString(1.1))->toBe('1');
    });

    it('converts string integers to integer strings', function (): void {
        expect(TypeTransformer::toIntegerString('5'))->toBe('5');
        expect(TypeTransformer::toIntegerString('999'))->toBe('999');
        expect(TypeTransformer::toIntegerString('1'))->toBe('1');
    });

    it('converts integers to decimal strings with 2 decimals', function (): void {
        expect(TypeTransformer::toDecimalString(5))->toBe('5.00');
        expect(TypeTransformer::toDecimalString(999))->toBe('999.00');
        expect(TypeTransformer::toDecimalString(0))->toBe('0.00');
    });

    it('converts floats to decimal strings with 2 decimals', function (): void {
        expect(TypeTransformer::toDecimalString(5.5))->toBe('5.50');
        expect(TypeTransformer::toDecimalString(5.1))->toBe('5.10');
        expect(TypeTransformer::toDecimalString(5.456))->toBe('5.46');
        expect(TypeTransformer::toDecimalString(5.454))->toBe('5.45');
    });

    it('converts strings to decimal strings with 2 decimals', function (): void {
        expect(TypeTransformer::toDecimalString('5'))->toBe('5.00');
        expect(TypeTransformer::toDecimalString('5.5'))->toBe('5.50');
        expect(TypeTransformer::toDecimalString('5.456'))->toBe('5.46');
    });

    it('supports custom decimal places', function (): void {
        expect(TypeTransformer::toDecimalString(5.456, 3))->toBe('5.456');
        expect(TypeTransformer::toDecimalString(5.4567, 3))->toBe('5.457');
        expect(TypeTransformer::toDecimalString(5, 4))->toBe('5.0000');
    });
});

describe('TypeTransformer - Context-Aware Methods', function (): void {
    describe('forItemWeight (GRAMS → integer)', function (): void {
        it('converts integer grams to integer string', function (): void {
            expect(TypeTransformer::forItemWeight(500))->toBe('500');
            expect(TypeTransformer::forItemWeight(1000))->toBe('1000');
            expect(TypeTransformer::forItemWeight(1))->toBe('1');
        });

        it('converts float grams to integer string (truncates)', function (): void {
            expect(TypeTransformer::forItemWeight(500.7))->toBe('500');
            expect(TypeTransformer::forItemWeight(999.99))->toBe('999');
        });

        it('converts string grams to integer string', function (): void {
            expect(TypeTransformer::forItemWeight('500'))->toBe('500');
            expect(TypeTransformer::forItemWeight('1000'))->toBe('1000');
        });
    });

    describe('forPackageWeight (KILOGRAMS → 2 decimals)', function (): void {
        it('converts integer kg to 2-decimal string', function (): void {
            expect(TypeTransformer::forPackageWeight(5))->toBe('5.00');
            expect(TypeTransformer::forPackageWeight(10))->toBe('10.00');
            expect(TypeTransformer::forPackageWeight(1))->toBe('1.00');
        });

        it('converts float kg to 2-decimal string', function (): void {
            expect(TypeTransformer::forPackageWeight(5.5))->toBe('5.50');
            expect(TypeTransformer::forPackageWeight(5.1))->toBe('5.10');
            expect(TypeTransformer::forPackageWeight(5.456))->toBe('5.46');
            expect(TypeTransformer::forPackageWeight(0.01))->toBe('0.01');
            expect(TypeTransformer::forPackageWeight(999.99))->toBe('999.99');
        });

        it('converts string kg to 2-decimal string', function (): void {
            expect(TypeTransformer::forPackageWeight('5'))->toBe('5.00');
            expect(TypeTransformer::forPackageWeight('5.5'))->toBe('5.50');
            expect(TypeTransformer::forPackageWeight('5.456'))->toBe('5.46');
        });
    });

    describe('forDimension (CENTIMETERS → 2 decimals)', function (): void {
        it('converts integer cm to 2-decimal string', function (): void {
            expect(TypeTransformer::forDimension(25))->toBe('25.00');
            expect(TypeTransformer::forDimension(50))->toBe('50.00');
            expect(TypeTransformer::forDimension(1))->toBe('1.00');
        });

        it('converts float cm to 2-decimal string', function (): void {
            expect(TypeTransformer::forDimension(25.5))->toBe('25.50');
            expect(TypeTransformer::forDimension(25.1))->toBe('25.10');
            expect(TypeTransformer::forDimension(25.756))->toBe('25.76');
            expect(TypeTransformer::forDimension(0.01))->toBe('0.01');
            expect(TypeTransformer::forDimension(999.99))->toBe('999.99');
        });

        it('converts string cm to 2-decimal string', function (): void {
            expect(TypeTransformer::forDimension('25'))->toBe('25.00');
            expect(TypeTransformer::forDimension('25.5'))->toBe('25.50');
            expect(TypeTransformer::forDimension('25.756'))->toBe('25.76');
        });
    });

    describe('forMoney (MALAYSIAN RINGGIT → 2 decimals)', function (): void {
        it('converts integer myr to 2-decimal string', function (): void {
            expect(TypeTransformer::forMoney(150))->toBe('150.00');
            expect(TypeTransformer::forMoney(1000))->toBe('1000.00');
            expect(TypeTransformer::forMoney(1))->toBe('1.00');
        });

        it('converts float myr to 2-decimal string', function (): void {
            expect(TypeTransformer::forMoney(19.9))->toBe('19.90');
            expect(TypeTransformer::forMoney(150.5))->toBe('150.50');
            expect(TypeTransformer::forMoney(150.456))->toBe('150.46');
            expect(TypeTransformer::forMoney(0.01))->toBe('0.01');
            expect(TypeTransformer::forMoney(999999.99))->toBe('999999.99');
        });

        it('converts string myr to 2-decimal string', function (): void {
            expect(TypeTransformer::forMoney('150'))->toBe('150.00');
            expect(TypeTransformer::forMoney('19.9'))->toBe('19.90');
            expect(TypeTransformer::forMoney('150.456'))->toBe('150.46');
        });
    });
});

describe('TypeTransformer - Boolean Methods', function (): void {
    it('converts boolean true to Y', function (): void {
        expect(TypeTransformer::toBooleanString(true))->toBe('Y');
    });

    it('converts boolean false to N', function (): void {
        expect(TypeTransformer::toBooleanString(false))->toBe('N');
    });

    it('converts string Y to Y (case insensitive)', function (): void {
        expect(TypeTransformer::toBooleanString('Y'))->toBe('Y');
        expect(TypeTransformer::toBooleanString('y'))->toBe('Y');
    });

    it('converts string N to N (case insensitive)', function (): void {
        expect(TypeTransformer::toBooleanString('N'))->toBe('N');
        expect(TypeTransformer::toBooleanString('n'))->toBe('N');
    });

    it('converts any non-Y string to N', function (): void {
        expect(TypeTransformer::toBooleanString('X'))->toBe('N');
        expect(TypeTransformer::toBooleanString(''))->toBe('N');
        expect(TypeTransformer::toBooleanString('false'))->toBe('N');
    });

    it('converts Y string to boolean true', function (): void {
        expect(TypeTransformer::fromBooleanString('Y'))->toBeTrue();
        expect(TypeTransformer::fromBooleanString('y'))->toBeTrue();
    });

    it('converts N string to boolean false', function (): void {
        expect(TypeTransformer::fromBooleanString('N'))->toBeFalse();
        expect(TypeTransformer::fromBooleanString('n'))->toBeFalse();
    });

    it('passes through boolean values', function (): void {
        expect(TypeTransformer::fromBooleanString(true))->toBeTrue();
        expect(TypeTransformer::fromBooleanString(false))->toBeFalse();
    });
});

describe('TypeTransformer - Real-World Scenarios', function (): void {
    it('handles item weight transformation correctly', function (): void {
        // Scenario: T-shirt weighing 250 grams
        expect(TypeTransformer::forItemWeight(250))->toBe('250');

        // Scenario: Book weighing 500.5 grams (truncate to integer)
        expect(TypeTransformer::forItemWeight(500.5))->toBe('500');

        // Scenario: Phone weighing 180 grams (from string)
        expect(TypeTransformer::forItemWeight('180'))->toBe('180');
    });

    it('handles package weight transformation correctly', function (): void {
        // Scenario: Small package 2.5 kg
        expect(TypeTransformer::forPackageWeight(2.5))->toBe('2.50');

        // Scenario: Medium package 5 kg (integer input)
        expect(TypeTransformer::forPackageWeight(5))->toBe('5.00');

        // Scenario: Large package 15.456 kg (rounds to 2dp)
        expect(TypeTransformer::forPackageWeight(15.456))->toBe('15.46');

        // Scenario: Minimum weight 0.01 kg
        expect(TypeTransformer::forPackageWeight(0.01))->toBe('0.01');
    });

    it('handles dimension transformation correctly', function (): void {
        // Scenario: Box 30x20x10 cm
        expect(TypeTransformer::forDimension(30))->toBe('30.00');
        expect(TypeTransformer::forDimension(20))->toBe('20.00');
        expect(TypeTransformer::forDimension(10))->toBe('10.00');

        // Scenario: Precise dimension 25.5 cm
        expect(TypeTransformer::forDimension(25.5))->toBe('25.50');

        // Scenario: Measured dimension 15.756 cm (rounds to 2dp)
        expect(TypeTransformer::forDimension(15.756))->toBe('15.76');
    });

    it('handles money transformation correctly', function (): void {
        // Scenario: Product price RM 19.90
        expect(TypeTransformer::forMoney(19.9))->toBe('19.90');

        // Scenario: COD amount RM 150 (integer input)
        expect(TypeTransformer::forMoney(150))->toBe('150.00');

        // Scenario: Declared value RM 1299.99
        expect(TypeTransformer::forMoney(1299.99))->toBe('1299.99');

        // Scenario: Small amount RM 0.50
        expect(TypeTransformer::forMoney(0.50))->toBe('0.50');
    });
});
