<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Cart\Cart;
use AIArmada\Events\Data\EventCartLineData;

interface EventCartDriver
{
    public function currentCartId(): ?string;

    public function instanceCartId(string $name, ?string $identifier = null): ?string;

    public function addLine(Cart $cart, EventCartLineData $line): void;

    public function addRequiredBundles(Cart $cart, string $ticketTypeId): void;

    public function startCheckout(string $cartId, ?string $customerId = null): mixed;
}
