<?php

declare(strict_types=1);

use AIArmada\Jnt\Enums\ErrorCode;

describe('ErrorCode Enum', function (): void {
    it('has correct integer values', function (): void {
        expect(ErrorCode::SUCCESS->value)->toBe(1);
        expect(ErrorCode::FAIL->value)->toBe(0);
        expect(ErrorCode::DIGEST_EMPTY->value)->toBe(145003052);
        expect(ErrorCode::API_ACCOUNT_EMPTY->value)->toBe(145003051);
        expect(ErrorCode::TIMESTAMP_EMPTY->value)->toBe(145003053);
        expect(ErrorCode::API_ACCOUNT_NOT_EXISTS->value)->toBe(145003010);
        expect(ErrorCode::API_ACCOUNT_NO_PERMISSION->value)->toBe(145003012);
        expect(ErrorCode::SIGNATURE_VERIFICATION_FAILED->value)->toBe(145003030);
        expect(ErrorCode::ILLEGAL_PARAMETERS->value)->toBe(145003050);
        expect(ErrorCode::DATA_NOT_FOUND->value)->toBe(999001030);
        expect(ErrorCode::DATA_NOT_FOUND_CANCEL->value)->toBe(999002000);
        expect(ErrorCode::ORDER_CANNOT_BE_CANCELLED->value)->toBe(999002010);
    });

    it('returns correct messages', function (): void {
        expect(ErrorCode::SUCCESS->getMessage())->toBe('Success');
        expect(ErrorCode::FAIL->getMessage())->toBe('Operation failed');
        expect(ErrorCode::DIGEST_EMPTY->getMessage())->toBe('Signature digest is empty');
        expect(ErrorCode::API_ACCOUNT_EMPTY->getMessage())->toBe('API account is empty');
        expect(ErrorCode::TIMESTAMP_EMPTY->getMessage())->toBe('Timestamp is empty');
        expect(ErrorCode::DATA_NOT_FOUND->getMessage())->toBe('Data cannot be found');
    });

    it('returns detailed descriptions', function (): void {
        expect(ErrorCode::SUCCESS->getDescription())->toContain('successfully');
        expect(ErrorCode::DIGEST_EMPTY->getDescription())->toContain('Signature Tools');
        expect(ErrorCode::API_ACCOUNT_EMPTY->getDescription())->toContain('Console');
        expect(ErrorCode::TIMESTAMP_EMPTY->getDescription())->toContain('milliseconds');
        expect(ErrorCode::SIGNATURE_VERIFICATION_FAILED->getDescription())->toContain('privateKey');
        expect(ErrorCode::DATA_NOT_FOUND->getDescription())->toContain('tracking number');
    });

    it('correctly identifies retryable errors', function (): void {
        expect(ErrorCode::TIMESTAMP_EMPTY->isRetryable())->toBeTrue();
        expect(ErrorCode::SIGNATURE_VERIFICATION_FAILED->isRetryable())->toBeTrue();
        expect(ErrorCode::ILLEGAL_PARAMETERS->isRetryable())->toBeTrue();

        expect(ErrorCode::API_ACCOUNT_NOT_EXISTS->isRetryable())->toBeFalse();
        expect(ErrorCode::DATA_NOT_FOUND->isRetryable())->toBeFalse();
        expect(ErrorCode::SUCCESS->isRetryable())->toBeFalse();
    });

    it('correctly identifies client errors', function (): void {
        expect(ErrorCode::DIGEST_EMPTY->isClientError())->toBeTrue();
        expect(ErrorCode::API_ACCOUNT_EMPTY->isClientError())->toBeTrue();
        expect(ErrorCode::ILLEGAL_PARAMETERS->isClientError())->toBeTrue();
        expect(ErrorCode::DATA_NOT_FOUND->isClientError())->toBeTrue();
        expect(ErrorCode::ORDER_CANNOT_BE_CANCELLED->isClientError())->toBeTrue();

        expect(ErrorCode::SUCCESS->isClientError())->toBeFalse();
        expect(ErrorCode::FAIL->isClientError())->toBeFalse();
    });

    it('correctly identifies server errors', function (): void {
        // J&T API does not have explicit server error codes
        expect(ErrorCode::SUCCESS->isServerError())->toBeFalse();
        expect(ErrorCode::FAIL->isServerError())->toBeFalse();
        expect(ErrorCode::DIGEST_EMPTY->isServerError())->toBeFalse();
        expect(ErrorCode::DATA_NOT_FOUND->isServerError())->toBeFalse();
    });

    it('returns correct categories', function (): void {
        expect(ErrorCode::SUCCESS->getCategory())->toBe('Success');
        expect(ErrorCode::FAIL->getCategory())->toBe('Generic');

        // Authentication
        expect(ErrorCode::DIGEST_EMPTY->getCategory())->toBe('Authentication');
        expect(ErrorCode::API_ACCOUNT_EMPTY->getCategory())->toBe('Authentication');
        expect(ErrorCode::SIGNATURE_VERIFICATION_FAILED->getCategory())->toBe('Authentication');

        // Validation
        expect(ErrorCode::ILLEGAL_PARAMETERS->getCategory())->toBe('Validation');
        expect(ErrorCode::CUSTOMER_CODE_REQUIRED->getCategory())->toBe('Validation');

        // Data
        expect(ErrorCode::DATA_NOT_FOUND->getCategory())->toBe('Data');
        expect(ErrorCode::DATA_NOT_FOUND_CANCEL->getCategory())->toBe('Data');

        // Business Logic
        expect(ErrorCode::ORDER_CANNOT_BE_CANCELLED->getCategory())->toBe('Business Logic');
    });

    it('correctly identifies success status', function (): void {
        expect(ErrorCode::SUCCESS->isSuccess())->toBeTrue();

        expect(ErrorCode::FAIL->isSuccess())->toBeFalse();
        expect(ErrorCode::DIGEST_EMPTY->isSuccess())->toBeFalse();
        expect(ErrorCode::DATA_NOT_FOUND->isSuccess())->toBeFalse();
    });

    it('correctly identifies failure status', function (): void {
        expect(ErrorCode::FAIL->isFailure())->toBeTrue();
        expect(ErrorCode::DIGEST_EMPTY->isFailure())->toBeTrue();
        expect(ErrorCode::DATA_NOT_FOUND->isFailure())->toBeTrue();

        expect(ErrorCode::SUCCESS->isFailure())->toBeFalse();
    });

    it('creates from API response code', function (): void {
        expect(ErrorCode::fromCode(1))->toBe(ErrorCode::SUCCESS);
        expect(ErrorCode::fromCode(0))->toBe(ErrorCode::FAIL);
        expect(ErrorCode::fromCode(145003052))->toBe(ErrorCode::DIGEST_EMPTY);
        expect(ErrorCode::fromCode(999001030))->toBe(ErrorCode::DATA_NOT_FOUND);

        // Unknown code returns null
        expect(ErrorCode::fromCode(999999))->toBeNull();
    });

    it('returns all authentication errors', function (): void {
        $authErrors = ErrorCode::authenticationErrors();

        expect($authErrors)->toHaveCount(6);
        expect($authErrors)->toContain(ErrorCode::DIGEST_EMPTY);
        expect($authErrors)->toContain(ErrorCode::API_ACCOUNT_EMPTY);
        expect($authErrors)->toContain(ErrorCode::TIMESTAMP_EMPTY);
        expect($authErrors)->toContain(ErrorCode::API_ACCOUNT_NOT_EXISTS);
        expect($authErrors)->toContain(ErrorCode::API_ACCOUNT_NO_PERMISSION);
        expect($authErrors)->toContain(ErrorCode::SIGNATURE_VERIFICATION_FAILED);
    });

    it('returns all validation errors', function (): void {
        $validationErrors = ErrorCode::validationErrors();

        expect($validationErrors)->toHaveCount(4);
        expect($validationErrors)->toContain(ErrorCode::ILLEGAL_PARAMETERS);
        expect($validationErrors)->toContain(ErrorCode::CUSTOMER_CODE_REQUIRED);
        expect($validationErrors)->toContain(ErrorCode::PASSWORD_REQUIRED);
        expect($validationErrors)->toContain(ErrorCode::TX_LOGISTIC_ID_REQUIRED);
    });

    it('returns all data errors', function (): void {
        $dataErrors = ErrorCode::dataErrors();

        expect($dataErrors)->toHaveCount(2);
        expect($dataErrors)->toContain(ErrorCode::DATA_NOT_FOUND);
        expect($dataErrors)->toContain(ErrorCode::DATA_NOT_FOUND_CANCEL);
    });

    it('returns all business logic errors', function (): void {
        $businessErrors = ErrorCode::businessLogicErrors();

        expect($businessErrors)->toHaveCount(1);
        expect($businessErrors)->toContain(ErrorCode::ORDER_CANNOT_BE_CANCELLED);
    });
});

describe('ErrorCode - Real-World Scenarios', function (): void {
    it('handles authentication failure scenario', function (): void {
        $error = ErrorCode::SIGNATURE_VERIFICATION_FAILED;

        expect($error->getCategory())->toBe('Authentication');
        expect($error->isClientError())->toBeTrue();
        expect($error->isRetryable())->toBeTrue(); // May be transient (clock skew)
        expect($error->getDescription())->toContain('privateKey');
    });

    it('handles data not found scenario', function (): void {
        $error = ErrorCode::DATA_NOT_FOUND;

        expect($error->getCategory())->toBe('Data');
        expect($error->isClientError())->toBeTrue();
        expect($error->isRetryable())->toBeFalse(); // Permanent error
        expect($error->getMessage())->toBe('Data cannot be found');
    });

    it('handles validation error scenario', function (): void {
        $error = ErrorCode::CUSTOMER_CODE_REQUIRED;

        expect($error->getCategory())->toBe('Validation');
        expect($error->isClientError())->toBeTrue();
        expect($error->isRetryable())->toBeFalse();
        expect($error->getDescription())->toContain('mandatory');
    });

    it('handles successful response scenario', function (): void {
        $error = ErrorCode::SUCCESS;

        expect($error->isSuccess())->toBeTrue();
        expect($error->isFailure())->toBeFalse();
        expect($error->isClientError())->toBeFalse();
        expect($error->getCategory())->toBe('Success');
    });

    it('handles order cancellation failure scenario', function (): void {
        $error = ErrorCode::ORDER_CANNOT_BE_CANCELLED;

        expect($error->getCategory())->toBe('Business Logic');
        expect($error->isClientError())->toBeTrue();
        expect($error->isRetryable())->toBeFalse();
        expect($error->getDescription())->toContain('current status');
    });
});
