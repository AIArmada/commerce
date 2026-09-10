<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Support;

use AIArmada\Ticketing\Contracts\TicketableInterface;
use InvalidArgumentException;

final class TicketableTypeRegistry
{
    /** @var array<int, class-string> */
    private array $types = [];

    /** @param class-string $class */
    public function register(string $class): void
    {
        if (! is_subclass_of($class, TicketableInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s',
                $class,
                TicketableInterface::class,
            ));
        }

        if (in_array($class, $this->types, true)) {
            return;
        }

        $this->types[] = $class;
    }

    /** @return array<int, class-string> */
    public function all(): array
    {
        foreach (config('ticketing.ticketable_types', []) as $class) {
            if (! is_string($class)) {
                continue;
            }

            $this->register($class);
        }

        $allowedTypes = config('ticketing.allowed_ticketable_types', []);
        $types = array_values($this->types);

        return $allowedTypes !== []
            ? array_values(array_intersect($types, $allowedTypes))
            : $types;
    }
}
