<?php

declare(strict_types=1);

use AIArmada\Chip\Exceptions\ChipApiException;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Exceptions\WebhookVerificationException;

describe('ChipApiException', function (): void {
    it('creates exception with message and status code', function (): void {
        $exception = new ChipApiException('API request failed', 400);

        expect($exception->getMessage())->toBe('API request failed');
        expect($exception->getStatusCode())->toBe(400);
        expect($exception->getErrorDetails())->toBe([]);
    });

    it('stores error details from API response', function (): void {
        $errorDetails = [
            'field' => 'amount_in_cents',
            'message' => 'Must be greater than 0',
        ];

        $exception = new ChipApiException('Validation failed', 422, $errorDetails);

        expect($exception->getErrorDetails())->toBe($errorDetails);
        expect($exception->hasErrorDetails())->toBeTrue();
    });

    it('handles empty error details', function (): void {
        $exception = new ChipApiException('Server error', 500);

        expect($exception->getErrorDetails())->toBe([]);
        expect($exception->hasErrorDetails())->toBeFalse();
    });

    it('exposes error code and message accessors', function (): void {
        $exception = new ChipApiException('Request failed', 400, [
            'code' => 'INVALID_REQUEST',
            'message' => 'Payload is invalid',
        ]);

        expect($exception->getErrorCode())->toBe('INVALID_REQUEST');
        expect($exception->getErrorMessage())->toBe('Payload is invalid');
    });

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

    it('converts the exception to array structure', function (): void {
        $exception = new ChipApiException('Failure', 503, ['code' => 'DOWNSTREAM']);

        expect($exception->toArray())->toBe([
            'message' => 'Failure',
            'status_code' => 503,
            'error_data' => ['code' => 'DOWNSTREAM'],
        ]);
    });
});

describe('ChipValidationException', function (): void {
    it('creates validation exception with field errors', function (): void {
        $errors = [
            'amount_in_cents' => ['Required field'],
            'currency' => ['Invalid currency code'],
        ];

        $exception = new ChipValidationException('Validation failed', $errors);

        expect($exception->getMessage())->toBe('Validation failed');
        expect($exception->getValidationErrors())->toBe($errors);
    });

    it('checks if specific field has error', function (): void {
        $errors = [
            'amount_in_cents' => ['Required field'],
            'currency' => ['Invalid currency code'],
        ];

        $exception = new ChipValidationException('Validation failed', $errors);

        expect($exception->hasError('amount_in_cents'))->toBeTrue();
        expect($exception->hasError('reference'))->toBeFalse();
    });

    it('gets errors for specific field', function (): void {
        $errors = [
            'amount_in_cents' => ['Required field', 'Must be positive'],
            'currency' => ['Invalid currency code'],
        ];

        $exception = new ChipValidationException('Validation failed', $errors);

        expect($exception->getFieldErrors('amount_in_cents'))->toBe(['Required field', 'Must be positive']);
        expect($exception->getFieldErrors('unknown_field'))->toBe([]);
    });

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

describe('WebhookVerificationException', function (): void {
    it('creates exception with verification failure message', function (): void {
        $exception = new WebhookVerificationException('Invalid signature');

        expect($exception->getMessage())->toBe('Invalid signature');
        expect($exception->getCode())->toBe(0);
    });

    it('creates exception for missing signature', function (): void {
        $exception = WebhookVerificationException::missingSignature();

        expect($exception->getMessage())->toContain('missing');
        expect($exception->getMessage())->toContain('signature');
    });

    it('creates exception for invalid signature format', function (): void {
        $exception = WebhookVerificationException::invalidSignatureFormat();

        expect($exception->getMessage())->toContain('invalid');
        expect($exception->getMessage())->toContain('format');
    });

    it('creates exception for signature verification failure', function (): void {
        $exception = WebhookVerificationException::verificationFailed();

        expect($exception->getMessage())->toContain('verification');
        expect($exception->getMessage())->toContain('failed');
    });

    it('creates exception for invalid payload', function (): void {
        $exception = WebhookVerificationException::invalidPayload('Malformed JSON');

        expect($exception->getMessage())->toContain('Invalid payload');
        expect($exception->getMessage())->toContain('Malformed JSON');
    });

    it('creates exception for missing public key', function (): void {
        $exception = WebhookVerificationException::missingPublicKey();

        expect($exception->getMessage())->toContain('public key');
        expect($exception->getMessage())->toContain('configured');
    });
});

describe('Exception Error Context', function (): void {
    it('preserves original exception context', function (): void {
        $previous = new Exception('Original error');
        $exception = new ChipApiException('API error', 500, [], $previous);

        expect($exception->getPrevious())->toBe($previous);
    });

    it('maintains error context through exception chain', function (): void {
        $httpException = new Exception('HTTP connection failed');
        $apiException = new ChipApiException('Request failed', 500, [], $httpException);

        expect($apiException->getPrevious())->toBe($httpException);
        expect($apiException->getPrevious()->getMessage())->toBe('HTTP connection failed');
    });
});
