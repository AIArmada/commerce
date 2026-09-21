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
