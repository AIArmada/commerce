<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Handlers;

use RuntimeException;

final class ConditionTypeHandlerRegistry
{
    /** @var array<string, ConditionTypeHandlerInterface> */
    private array $handlers = [];

    public function register(ConditionTypeHandlerInterface $handler): void
    {
        $this->handlers[$handler->type()] = $handler;
    }

    public function has(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    public function get(string $type): ConditionTypeHandlerInterface
    {
        if (! isset($this->handlers[$type])) {
            throw new RuntimeException("No condition type handler registered for '{$type}'.");
        }

        return $this->handlers[$type];
    }

    public function all(): array
    {
        return $this->handlers;
    }
}
