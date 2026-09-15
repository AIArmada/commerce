<?php

declare(strict_types=1);

use AIArmada\Cart\Exceptions\CartException;
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

it('uses the default message and cart exception hierarchy', function (): void {
    $exception = new UnknownModelException;

    expect($exception->getMessage())->toBe('Unknown model class')
        ->and($exception)->toBeInstanceOf(CartException::class);
});
