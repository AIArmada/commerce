<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Support\Str;

final readonly class CheckoutPaymentReference
{
    private const string PREFIX = 'chk_';

    public static function forSession(CheckoutSession $session): string
    {
        return self::PREFIX . $session->getKey();
    }

    public static function normalizeChipReference(string $reference): ?string
    {
        if (self::sessionId($reference) !== null) {
            return $reference;
        }

        return Str::isUuid($reference) ? self::PREFIX . $reference : null;
    }

    public static function sessionId(string $reference): ?string
    {
        if (! str_starts_with($reference, self::PREFIX)) {
            return null;
        }

        $sessionId = mb_substr($reference, mb_strlen(self::PREFIX));

        return Str::isUuid($sessionId) ? $sessionId : null;
    }
}
