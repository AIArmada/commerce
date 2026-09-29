<?php

declare(strict_types=1);

use AIArmada\Chip\Exceptions\ChipApiException;
use AIArmada\Chip\Exceptions\ChipValidationException;

describe('ChipApiException', function (): void {
    it('formats error message with details', function (): void {
        $errorDetails = [
            'errors' => [
                'amount_in_cents' => ['Must be greater than 0'],
                'currency' => ['Invalid currency code'],
            ],
        ];

        $exception = new ChipApiException('Validation failed', 422, $errorDetails);

        expect($exception->getFormattedMessage())->toContain('Validation failed');
        expect($exception->getFormattedMessage())->toContain('amount_in_cents');
        expect($exception->getFormattedMessage())->toContain('currency');
    });

    it('creates exception from HTTP response', function (): void {
        $responseData = [
            'error' => 'Insufficient funds',
            'code' => 'INSUFFICIENT_FUNDS',
            'details' => ['available_balance' => 5000],
        ];

        $exception = ChipApiException::fromResponse($responseData, 402);

        expect($exception->getMessage())->toBe('Insufficient funds');
        expect($exception->getStatusCode())->toBe(402);
        expect($exception->getErrorDetails())->toBe([
            'code' => 'INSUFFICIENT_FUNDS',
            'details' => ['available_balance' => 5000],
        ]);
    });

    it('handles missing error message in response', function (): void {
        $responseData = [
            'code' => 'UNKNOWN_ERROR',
            'details' => ['timestamp' => '2024-01-01T12:00:00Z'],
        ];

        $exception = ChipApiException::fromResponse($responseData, 500);

        expect($exception->getMessage())->toBe('Unknown API error');
        expect($exception->getErrorDetails())->toBe($responseData);
    });

    it('extracts object-shaped __all__ errors', function (): void {
        $exception = ChipApiException::fromResponse([
            '__all__' => ['message' => 'descriptive error message', 'code' => 'error_code'],
        ], 400);

        expect($exception->getMessage())->toBe('descriptive error message')
            ->and($exception->getErrorCode())->toBe('error_code')
            ->and($exception->getErrorMessage())->toBe('descriptive error message');
    });

    it('extracts list-shaped __all__ errors', function (): void {
        $exception = ChipApiException::fromResponse([
            '__all__' => [
                ['message' => 'Invalid or inactive recurring token!', 'code' => 'invalid_recurring_token'],
            ],
        ], 400);

        expect($exception->getMessage())->toBe('Invalid or inactive recurring token!')
            ->and($exception->getErrorCode())->toBe('invalid_recurring_token')
            ->and($exception->getErrorMessage())->toBe('Invalid or inactive recurring token!');
    });

    it('prefers top-level error fields over __all__', function (): void {
        $exception = ChipApiException::fromResponse([
            'error' => 'Top level',
            'code' => 'TOP_LEVEL',
            '__all__' => ['message' => 'Nested', 'code' => 'NESTED'],
        ], 400);

        expect($exception->getMessage())->toBe('Top level')
            ->and($exception->getErrorCode())->toBe('TOP_LEVEL');
    });

    it('extracts a message-only error from a string __all__ item', function (): void {
        $exception = ChipApiException::fromResponse([
            '__all__' => ['plain failure'],
        ], 400);

        expect($exception->getMessage())->toBe('plain failure')
            ->and($exception->getErrorCode())->toBeNull()
            ->and($exception->getErrorMessage())->toBe('plain failure');
    });

    it('ignores a malformed __all__ payload', function (): void {
        $exception = ChipApiException::fromResponse([
            '__all__' => 42,
        ], 400);

        expect($exception->getMessage())->toBe('Unknown API error')
            ->and($exception->getErrorCode())->toBeNull();
    });

    it('ignores a non-string __all__ error object', function (): void {
        $exception = ChipApiException::fromResponse([
            '__all__' => ['message' => 42, 'code' => 99],
        ], 400);

        expect($exception->getMessage())->toBe('Unknown API error')
            ->and($exception->getErrorCode())->toBeNull();
    });

    it('uses a scalar string body as the message', function (): void {
        $exception = ChipApiException::fromResponse('Too many requests', 502);

        expect($exception->getMessage())->toBe('Too many requests')
            ->and($exception->getErrorCode())->toBeNull();
    });

    it('falls back to an unknown error on a non-string scalar body', function (): void {
        $exception = ChipApiException::fromResponse(42, 502);

        expect($exception->getMessage())->toBe('Unknown API error')
            ->and($exception->getErrorCode())->toBeNull();
    });

    it('returns null extracting __all__ from a scalar body', function (): void {
        expect(ChipApiException::extractAllError('Too many requests'))->toBeNull()
            ->and(ChipApiException::extractAllError(42))->toBeNull();
    });

});

describe('ChipValidationException', function (): void {
    it('formats all validation errors as string', function (): void {
        $errors = [
            'amount_in_cents' => ['Required field'],
            'currency' => ['Invalid currency code'],
        ];

        $exception = new ChipValidationException('Validation failed', $errors);
        $formatted = $exception->getFormattedErrors();

        expect($formatted)->toContain('amount_in_cents: Required field');
        expect($formatted)->toContain('currency: Invalid currency code');
    });

    it('creates exception from Laravel validator', function (): void {
        $validator = validator([
            'amount_in_cents' => null,
            'currency' => 'INVALID',
        ], [
            'amount_in_cents' => 'required|integer|min:1',
            'currency' => 'required|in:MYR,USD,SGD',
        ]);

        // Check if validation fails without throwing exception
        expect($validator->fails())->toBeTrue();

        $exception = ChipValidationException::fromValidator($validator);

        expect($exception)->toBeInstanceOf(ChipValidationException::class);
        expect($exception->hasError('amount_in_cents'))->toBeTrue();
        expect($exception->hasError('currency'))->toBeTrue();
    });
});
