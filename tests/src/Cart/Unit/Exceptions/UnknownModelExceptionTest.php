<?php

declare(strict_types=1);

use AIArmada\Cart\Exceptions\UnknownModelException;

it('can be instantiated with message', function (): void {
    $message = 'Unknown model class provided';
    $exception = new UnknownModelException($message);

    expect($exception->getMessage())->toBe($message)
        ->and($exception)->toBeInstanceOf(Exception::class);
});

it('can be instantiated with message and code', function (): void {
    $message = 'Model not found';
    $code = 404;
    $exception = new UnknownModelException($message, $code);

    expect($exception->getMessage())->toBe($message)
        ->and($exception->getCode())->toBe($code);
});

it('can be instantiated with message, code and previous exception', function (): void {
    $previous = new RuntimeException('Previous error');
    $message = 'Model configuration error';
    $code = 500;

    $exception = new UnknownModelException($message, $code, $previous);

    expect($exception->getMessage())->toBe($message)
        ->and($exception->getCode())->toBe($code)
        ->and($exception->getPrevious())->toBe($previous);
});

it('extends exception class', function (): void {
    $exception = new UnknownModelException('Test');

    expect($exception)->toBeInstanceOf(Exception::class);
});

it('can be thrown and caught', function (): void {
    $message = 'Test exception throwing';

    expect(function () use ($message): void {
        throw new UnknownModelException($message);
    })->toThrow(UnknownModelException::class, $message);
});

it('maintains proper exception hierarchy', function (): void {
    $exception = new UnknownModelException('Test');

    expect($exception)->toBeInstanceOf(Throwable::class)
        ->and($exception)->toBeInstanceOf(Exception::class);
});

it('can include model class information in message', function (): void {
    $modelClass = 'App\Models\NonExistentModel';
    $message = "Unknown model class: {$modelClass}";

    $exception = new UnknownModelException($message);

    expect($exception->getMessage())->toContain($modelClass);
});
