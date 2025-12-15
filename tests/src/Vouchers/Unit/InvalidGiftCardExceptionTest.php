<?php

declare(strict_types=1);

use AIArmada\Vouchers\GiftCards\Exceptions\InvalidGiftCardException;

uses()->group('gift_cards');

it('can be instantiated', function (): void {
    expect(new InvalidGiftCardException())->toBeInstanceOf(InvalidGiftCardException::class);
});

it('can set a custom message', function (): void {
    $message = 'This is a custom message';
    $exception = new InvalidGiftCardException($message);

    expect($exception->getMessage())->toBe($message);
});

it('can set a custom code', function (): void {
    $code = 404;
    $exception = new InvalidGiftCardException('Not Found', $code);

    expect($exception->getCode())->toBe($code);
});
