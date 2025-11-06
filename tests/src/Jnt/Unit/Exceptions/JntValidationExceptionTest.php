<?php

declare(strict_types=1);

use AIArmada\Jnt\Exceptions\JntValidationException;

describe('JntValidationException', function (): void {
    it('creates exception for field validation failure', function (): void {
        $errors = ['email' => ['Invalid format']];
        $exception = JntValidationException::fieldValidationFailed('email', 'Invalid format', $errors);

        expect($exception)
            ->toBeInstanceOf(JntValidationException::class)
            ->getMessage()->toBe("Validation failed for field 'email': Invalid format")
            ->and($exception->field)->toBe('email')
            ->and($exception->errors)->toBe($errors);
    });

    it('creates exception for required field missing', function (): void {
        $exception = JntValidationException::requiredFieldMissing('sender_name');

        expect($exception)
            ->toBeInstanceOf(JntValidationException::class)
            ->getMessage()->toBe("Required field 'sender_name' is missing")
            ->and($exception->field)->toBe('sender_name')
            ->and($exception->errors)->toBe(['sender_name' => ['The field is required']]);
    });

    it('creates exception for invalid field value with string', function (): void {
        $exception = JntValidationException::invalidFieldValue('quantity', 'abc', 'integer');

        expect($exception)
            ->toBeInstanceOf(JntValidationException::class)
            ->getMessage()->toContain('expected integer, got abc')
            ->and($exception->field)->toBe('quantity');
    });

    it('creates exception for invalid field value with array', function (): void {
        $exception = JntValidationException::invalidFieldValue('price', ['invalid'], 'number');

        expect($exception)
            ->toBeInstanceOf(JntValidationException::class)
            ->getMessage()->toContain('expected number, got array')
            ->and($exception->field)->toBe('price');
    });

    it('creates exception for field too long', function (): void {
        $exception = JntValidationException::fieldTooLong('name', 50, 75);

        expect($exception)
            ->toBeInstanceOf(JntValidationException::class)
            ->getMessage()->toContain('exceeds maximum length of 50 characters (got 75)')
            ->and($exception->field)->toBe('name')
            ->and($exception->errors)->toBe(['name' => ['Maximum length is 50 characters']]);
    });

    it('creates exception for field too short', function (): void {
        $exception = JntValidationException::fieldTooShort('password', 8, 5);

        expect($exception)
            ->toBeInstanceOf(JntValidationException::class)
            ->getMessage()->toContain('below minimum length of 8 characters (got 5)')
            ->and($exception->field)->toBe('password');
    });

    it('creates exception for value out of range', function (): void {
        $exception = JntValidationException::valueOutOfRange('weight', 0.1, 30.0, 35.5);

        expect($exception)
            ->toBeInstanceOf(JntValidationException::class)
            ->getMessage()->toContain('value 35.5 is outside valid range (0.1-30)')
            ->and($exception->field)->toBe('weight')
            ->and($exception->errors)->toBe(['weight' => ['Value must be between 0.1 and 30']]);
    });

    it('creates exception for invalid format without value', function (): void {
        $exception = JntValidationException::invalidFormat('email', 'valid email address');

        expect($exception)
            ->toBeInstanceOf(JntValidationException::class)
            ->getMessage()->toBe("Field 'email' has invalid format: expected valid email address")
            ->and($exception->field)->toBe('email');
    });

    it('creates exception for invalid format with value', function (): void {
        $exception = JntValidationException::invalidFormat('phone', 'E.164 format', '123');

        expect($exception)
            ->toBeInstanceOf(JntValidationException::class)
            ->getMessage()->toContain("expected E.164 format, got '123'")
            ->and($exception->field)->toBe('phone');
    });

    it('creates exception for multiple validation errors', function (): void {
        $errors = [
            'name' => ['Required', 'Too short'],
            'email' => ['Invalid format'],
            'phone' => ['Required'],
        ];
        $exception = JntValidationException::multiple($errors);

        expect($exception)
            ->toBeInstanceOf(JntValidationException::class)
            ->getMessage()->toContain('Validation failed for 3 field(s)')
            ->getMessage()->toContain('name, email, phone')
            ->and($exception->errors)->toBe($errors)
            ->and($exception->field)->toBeNull();
    });

    it('stores all errors for multiple fields', function (): void {
        $errors = [
            'field1' => ['Error 1', 'Error 2'],
            'field2' => ['Error 3'],
        ];
        $exception = JntValidationException::multiple($errors);

        expect($exception->errors)
            ->toBe($errors)
            ->toHaveKey('field1')
            ->toHaveKey('field2');
    });
});
