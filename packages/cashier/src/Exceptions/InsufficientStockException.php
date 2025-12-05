<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

use Exception;

/**
 * Exception thrown when cart items have insufficient stock.
 */
class InsufficientStockException extends Exception
{
    /** @var array<string, mixed> */
    private array $insufficientItems;

    /**
     * @param  array<string, mixed>  $insufficientItems
     */
    public function __construct(string $message, array $insufficientItems = [])
    {
        parent::__construct($message);
        $this->insufficientItems = $insufficientItems;
    }

    /**
     * Get the items with insufficient stock.
     *
     * @return array<string, mixed>
     */
    public function getInsufficientItems(): array
    {
        return $this->insufficientItems;
    }
}
