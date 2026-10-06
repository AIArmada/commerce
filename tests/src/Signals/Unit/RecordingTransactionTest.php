<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Exceptions\CommerceSignalTransactionControlFailed;
use AIArmada\Signals\Support\RecordingTransaction;
use AIArmada\Signals\Support\TransactionFailureClassifier;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

uses(SignalsTestCase::class);

function recordingTransactionConcurrencyFailure(): QueryException
{
    $pdo = new PDOException('could not serialize access due to concurrent update');

    // Real PDO drivers carry string SQLSTATE codes internally.
    (new ReflectionProperty(PDOException::class, 'code'))->setValue($pdo, '40001');

    return new QueryException('sqlite', 'update "probe" set "name" = ?', ['probe'], $pdo);
}

it('returns the callback value when the transaction succeeds', function (): void {
    expect(RecordingTransaction::run(static fn (): string => 'recorded'))->toBe('recorded');
});

it('rethrows a callback failure unchanged when the rollback succeeds', function (): void {
    $failure = new RuntimeException('callback boom');

    try {
        RecordingTransaction::run(static function () use ($failure): void {
            throw $failure;
        });

        $this->fail('The callback failure should have propagated.');
    } catch (Throwable $e) {
        expect($e)->toBe($failure);
    }
});

it('propagates concurrency failures untouched instead of marking them', function (): void {
    $failure = recordingTransactionConcurrencyFailure();

    try {
        RecordingTransaction::run(static function () use ($failure): void {
            throw $failure;
        });

        $this->fail('The concurrency failure should have propagated.');
    } catch (Throwable $e) {
        // Nested handling replaces the original with a DeadlockException;
        // either way the failure stays unmarked for host retry.
        expect($e)->not->toBeInstanceOf(CommerceSignalTransactionControlFailed::class);

        $chain = [$e];

        while (($previous = end($chain)->getPrevious()) !== null) {
            $chain[] = $previous;
        }

        expect($chain)->toContain($failure);
    }
});

it('propagates lost connections and deadlocks untouched', function (): void {
    $lost = new RuntimeException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');

    try {
        RecordingTransaction::run(static function () use ($lost): void {
            throw $lost;
        });

        $this->fail('The lost connection should have propagated.');
    } catch (Throwable $e) {
        expect($e)->toBe($lost);
    }

    $deadlock = new DeadlockException('custom deadlock boom');

    try {
        RecordingTransaction::run(static function () use ($deadlock): void {
            throw $deadlock;
        });

        $this->fail('The deadlock should have propagated.');
    } catch (Throwable $e) {
        expect($e)->toBe($deadlock);
    }
});

it('preserves an existing marker through a surrounding transaction', function (): void {
    $marker = CommerceSignalTransactionControlFailed::fromControlFailure(new RuntimeException('deeper rollback boom'));

    try {
        RecordingTransaction::run(static function () use ($marker): void {
            throw $marker;
        });

        $this->fail('The marker should have propagated.');
    } catch (Throwable $e) {
        expect($e)->toBe($marker);
    }
});

it('marks a begin failure that runs no callback', function (): void {
    $beginFailure = new RuntimeException('begin boom');
    $callbackRan = false;
    $hookFired = false;

    DB::beforeStartingTransaction(function () use ($beginFailure, &$hookFired): void {
        $hookFired = true;

        throw $beginFailure;
    });

    try {
        RecordingTransaction::run(static function () use (&$callbackRan): string {
            $callbackRan = true;

            return 'unreached';
        });

        $this->fail('The begin failure should have been marked.');
    } catch (CommerceSignalTransactionControlFailed $e) {
        expect($e->getPrevious())->toBe($beginFailure)
            ->and($e->context())->toMatchArray(['control_failure_class' => RuntimeException::class])
            ->and($e->context())->not->toHaveKey('callback_failure_class');
    }

    expect($hookFired)->toBeTrue()
        ->and($callbackRan)->toBeFalse();
});

it('marks recognized machinery failures while identity passes through', function (): void {
    $concurrency = recordingTransactionConcurrencyFailure();
    $lost = new RuntimeException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
    $deadlock = new DeadlockException('custom boom', 0, new RuntimeException('inner'));
    $callback = new RuntimeException('callback boom');

    // Provenance beats recognition: an escaped failure that differs from
    // the callback failure is transaction machinery, so a lost
    // connection during rollback is marked even though the same failure
    // from the callback itself would propagate untouched.
    $markedConcurrency = RecordingTransaction::classifyEscaped($concurrency, $callback);
    $markedLost = RecordingTransaction::classifyEscaped($lost, $callback);

    expect($markedConcurrency)->toBeInstanceOf(CommerceSignalTransactionControlFailed::class)
        ->and($markedConcurrency->getPrevious())->toBe($concurrency)
        ->and($markedConcurrency->context())->toMatchArray([
            'control_failure_class' => QueryException::class,
            'callback_failure_class' => RuntimeException::class,
        ])
        ->and($markedLost)->toBeInstanceOf(CommerceSignalTransactionControlFailed::class)
        ->and($markedLost->getPrevious())->toBe($lost)
        ->and($markedLost->context())->toMatchArray([
            'control_failure_class' => RuntimeException::class,
            'callback_failure_class' => RuntimeException::class,
        ])
        ->and(RecordingTransaction::classifyEscaped($deadlock, $callback))->toBe($deadlock)
        ->and(RecordingTransaction::classifyEscaped($callback, $callback))->toBe($callback);
});

it('marks unrecognized machinery failures with both failure classes', function (): void {
    $escaped = new QueryException('sqlite', 'ROLLBACK TO SAVEPOINT trans4', [], new RuntimeException('no such savepoint: trans4'));
    $callback = new RuntimeException('callback boom');

    $marked = RecordingTransaction::classifyEscaped($escaped, $callback);

    expect($marked)->toBeInstanceOf(CommerceSignalTransactionControlFailed::class)
        ->and($marked->getPrevious())->toBe($escaped)
        ->and($marked->context())->toMatchArray([
            'control_failure_class' => QueryException::class,
            'callback_failure_class' => RuntimeException::class,
        ])
        // The marker message must stay detector-neutral so surrounding
        // transactions never mistake it for a retryable failure.
        ->and(TransactionFailureClassifier::causedByConcurrencyError($marked))->toBeFalse()
        ->and(TransactionFailureClassifier::causedByLostConnection($marked))->toBeFalse()
        ->and($marked->getMessage())->not->toContain('no such savepoint');
});

it('marks a begin failure without callback context', function (): void {
    $escaped = new RuntimeException('begin boom');

    $marked = RecordingTransaction::classifyEscaped($escaped, null);

    expect($marked)->toBeInstanceOf(CommerceSignalTransactionControlFailed::class)
        ->and($marked->getPrevious())->toBe($escaped)
        ->and($marked->context())->not->toHaveKey('callback_failure_class');
});
