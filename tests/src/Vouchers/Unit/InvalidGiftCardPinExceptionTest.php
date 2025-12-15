<?php

declare(strict_types=1);

use AIArmada\Vouchers\GiftCards\Exceptions\InvalidGiftCardPinException;

uses()->group('gift_cards');


it('can be instantiated', function (): void {
    expect(new InvalidGiftCardPinException('TEST-CODE'))->toBeInstanceOf(InvalidGiftCardPinException::class);
});


it('can set a custom message', function (): void {
    $message = 'This is a custom message';
    $exception = new InvalidGiftCardPinException('TEST-CODE', $message);

    expect($exception->getMessage())->toBe($message);
});


it('can set a custom code', function (): void {
    $code = 404;
    $exception = new InvalidGiftCardPinException('TEST-CODE', 'Not Found', $code);

    expect($exception->getCode())->toBe($code);
});


it('can get the gift card code', function (): void {
    $giftCardCode = 'GIFT-CARD-CODE';
    $exception = new InvalidGiftCardPinException($giftCardCode);

    expect($exception->getGiftCardCode())->toBe($giftCardCode);
});
