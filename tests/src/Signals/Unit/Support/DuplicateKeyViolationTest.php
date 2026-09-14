<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Support\DuplicateKeyViolation;
use Illuminate\Database\QueryException;

uses(SignalsTestCase::class);

function duplicateViolationException(string $code, ?array $errorInfo): QueryException
{
    $previous = new PDOException('statement failed');
    $previous->errorInfo = $errorInfo;

    $exception = new QueryException('testing', 'insert into probe values (?)', [], $previous);

    $setCode = Closure::bind(function (string $code): void {
        $this->code = $code;
    }, $exception, $exception);
    $setCode($code);

    return $exception;
}

it('detects unique violations across database drivers', function (): void {
    expect(DuplicateKeyViolation::is(duplicateViolationException('23505', ['23505', 7, 'duplicate key value violates unique constraint'])))->toBeTrue()
        ->and(DuplicateKeyViolation::is(duplicateViolationException('23000', ['23000', 1062, "Duplicate entry 'x' for key 'sessions.session_identifier'"])))->toBeTrue()
        ->and(DuplicateKeyViolation::is(duplicateViolationException('23000', ['23000', 19, 'UNIQUE constraint failed: signal_sessions.session_identifier'])))->toBeTrue();
});

it('rejects non-duplicate integrity violations', function (): void {
    expect(DuplicateKeyViolation::is(duplicateViolationException('23000', ['23000', 19, 'CHECK constraint failed: signal_events'])))->toBeFalse()
        ->and(DuplicateKeyViolation::is(duplicateViolationException('23000', ['23000', 1452, 'Cannot add or update a child row'])))->toBeFalse()
        ->and(DuplicateKeyViolation::is(duplicateViolationException('HY000', null)))->toBeFalse();
});
