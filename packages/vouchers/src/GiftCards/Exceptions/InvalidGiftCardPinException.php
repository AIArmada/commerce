<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\GiftCards\Exceptions;

use Exception;

/**
 * Exception thrown when an incorrect PIN is provided for a gift card.
 */
class InvalidGiftCardPinException extends Exception
{
    private string $giftCardCode;

    public function __construct(
        string $giftCardCode,
        string $message = 'Invalid gift card PIN',
        int $errorCode = 0,
        ?\Throwable $previous = null
    ) {
        $this->giftCardCode = $giftCardCode;
        parent::__construct($message, $errorCode, $previous);
    }

    /**
     * Get the gift card code that had the invalid PIN.
     */
    public function getGiftCardCode(): string
    {
        return $this->giftCardCode;
    }
}
