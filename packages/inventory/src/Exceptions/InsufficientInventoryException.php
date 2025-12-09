<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Exceptions;

use Exception;

/**
 * Exception thrown when there is insufficient inventory for an operation.
 */
class InsufficientInventoryException extends Exception
{
    public function __construct(
        string $message,
        private readonly string | int $itemId,
        private readonly int $requestedQuantity,
        private readonly int $availableQuantity,
    ) {
        parent::__construct($message);
    }

    public function getItemId(): string | int
    {
        return $this->itemId;
    }

    public function getRequestedQuantity(): int
    {
        return $this->requestedQuantity;
    }

    public function getAvailableQuantity(): int
    {
        return $this->availableQuantity;
    }

    public function getShortfall(): int
    {
        return $this->requestedQuantity - $this->availableQuantity;
    }
}
