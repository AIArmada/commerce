<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Exceptions\CommerceSignalTransactionControlFailed;
use AIArmada\Signals\Support\TransactionFailureClassifier;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use PDOException;

uses(SignalsTestCase::class);

function pdoExceptionWithCode(string $message, string $code): PDOException
{
    $pdo = new PDOException($message);

    // Real PDO drivers carry string SQLSTATE codes internally.
    (new ReflectionProperty(PDOException::class, 'code'))->setValue($pdo, $code);

    return $pdo;
}

it('recognizes concurrency failures by code and message', function (): void {
    $pdo = pdoExceptionWithCode('could not serialize access due to concurrent update', '40001');

    expect(TransactionFailureClassifier::causedByConcurrencyError($pdo))->toBeTrue()
        ->and(TransactionFailureClassifier::causedByConcurrencyError(new RuntimeException('Deadlock found when trying to get lock; try restarting transaction')))->toBeTrue()
        ->and(TransactionFailureClassifier::causedByConcurrencyError(new RuntimeException('SQLSTATE[23000]: Integrity constraint violation')))->toBeFalse()
        ->and(TransactionFailureClassifier::causedByConcurrencyError(new InvalidArgumentException('missing field')))->toBeFalse();
});

it('recognizes lost connections', function (): void {
    expect(TransactionFailureClassifier::causedByLostConnection(new RuntimeException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away')))->toBeTrue()
        ->and(TransactionFailureClassifier::causedByLostConnection(new RuntimeException('SQLSTATE[23000]: Integrity constraint violation')))->toBeFalse();
});

it('unwraps nested deadlock replacements to the recognized root cause', function (): void {
    $pdo = pdoExceptionWithCode('could not serialize access due to concurrent update', '40001');

    $query = new QueryException('pgsql', 'update "signals_tracked_properties" set "name" = ?', ['probe'], $pdo);

    // Laravel nested handling replaces the original with code 0 when the
    // original code is a string, destroying SQLSTATE classification.
    $inner = new DeadlockException($query->getMessage(), 0, $query);
    $outer = new DeadlockException($inner->getMessage(), 0, $inner);

    expect(TransactionFailureClassifier::causedByConcurrencyError($outer))->toBeFalse()
        ->and(TransactionFailureClassifier::unwrapForHostRetry($outer))->toBe($pdo);
});

it('passes message-recognized deadlocks through unchanged', function (): void {
    $deadlock = new DeadlockException('Deadlock found when trying to get lock; try restarting transaction');

    expect(TransactionFailureClassifier::unwrapForHostRetry($deadlock))->toBe($deadlock);
});

it('passes custom deadlocks without a recognized cause through unchanged', function (): void {
    $deadlock = new DeadlockException('custom boom', 0, new RuntimeException('inner'));

    expect(TransactionFailureClassifier::unwrapForHostRetry($deadlock))->toBe($deadlock);
});

it('unwraps recognized contention from a control marker for host retry', function (): void {
    $pdo = pdoExceptionWithCode('could not serialize access due to concurrent update', '40001');
    $control = new QueryException('pgsql', 'update "probe" set "name" = ?', ['probe'], $pdo);
    $marker = CommerceSignalTransactionControlFailed::fromControlFailure($control, new RuntimeException('callback boom'));

    // The marker itself stays detector-neutral, so the host would never
    // retry it; the unwrap restores the retry classification explicitly.
    expect(TransactionFailureClassifier::causedByConcurrencyError($marker))->toBeFalse()
        ->and(TransactionFailureClassifier::unwrapMarkerForHostRetry($marker))->toBe($control);
});

it('unwraps a deadlock control cause through nested replacements', function (): void {
    $pdo = pdoExceptionWithCode('could not serialize access due to concurrent update', '40001');
    $query = new QueryException('pgsql', 'update "probe" set "name" = ?', ['probe'], $pdo);
    $inner = new DeadlockException($query->getMessage(), 0, $query);
    $outer = new DeadlockException($inner->getMessage(), 0, $inner);
    $marker = CommerceSignalTransactionControlFailed::fromControlFailure($outer);

    expect(TransactionFailureClassifier::unwrapMarkerForHostRetry($marker))->toBe($pdo);
});

it('keeps lost connections and unrecognized control failures marked', function (): void {
    $lost = new RuntimeException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
    $lostMarker = CommerceSignalTransactionControlFailed::fromControlFailure($lost, new RuntimeException('callback boom'));
    $plainMarker = CommerceSignalTransactionControlFailed::fromControlFailure(new RuntimeException('savepoint boom'));

    expect(TransactionFailureClassifier::unwrapMarkerForHostRetry($lostMarker))->toBe($lostMarker)
        ->and(TransactionFailureClassifier::unwrapMarkerForHostRetry($plainMarker))->toBe($plainMarker);
});
