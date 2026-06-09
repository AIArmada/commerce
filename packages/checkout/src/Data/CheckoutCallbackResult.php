<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

use AIArmada\Checkout\Models\CheckoutSession;

final readonly class CheckoutCallbackResult
{
    public function __construct(
        public ?CheckoutSession $session,
        public bool $alreadyCompleted,
        public ?CheckoutResult $result,
        public bool $sessionNotFound,
    ) {}

    public static function notFound(): self
    {
        return new self(
            session: null,
            alreadyCompleted: false,
            result: null,
            sessionNotFound: true,
        );
    }

    public static function alreadyCompleted(CheckoutSession $session): self
    {
        return new self(
            session: $session,
            alreadyCompleted: true,
            result: null,
            sessionNotFound: false,
        );
    }

    public static function processed(CheckoutSession $session, ?CheckoutResult $result = null): self
    {
        return new self(
            session: $session,
            alreadyCompleted: false,
            result: $result,
            sessionNotFound: false,
        );
    }
}
