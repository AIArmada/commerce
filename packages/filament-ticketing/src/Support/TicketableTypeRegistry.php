<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Support;

use InvalidArgumentException;

final class TicketableTypeRegistry
{
    public const TICKETABLE_INTERFACE = 'AIArmada\Ticketing\Contracts\TicketableInterface';

    /** @var array<int, class-string> */
    private array $types = [];

    /** @param class-string $class */
    public function register(string $class): void
    {
        if (! is_subclass_of($class, self::TICKETABLE_INTERFACE)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement %s',
                $class,
                self::TICKETABLE_INTERFACE,
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
        foreach (config('filament-ticketing.ticketable_types', []) as $class) {
            if (! is_string($class)) {
                continue;
            }

            $this->register($class);
        }

        $override = config('filament-ticketing.allowed_ticketable_types', []);
        $types = array_values($this->types);

        return $override !== []
            ? array_values(array_intersect($types, $override))
            : $types;
    }
}
