<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\TicketTypes\Actions;

use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

final readonly class ExpandTicketTypeComponentsAction
{
    /** @return Collection<int, TicketType> */
    public function handle(TicketType $parentTicketType, int $multiplier = 1): Collection
    {
        if ($multiplier < 1) {
            throw new InvalidArgumentException('Component multiplier must be positive.');
        }

        $parentTicketType->loadMissing('components.componentTicketType');

        if ($parentTicketType->components->isEmpty()) {
            return new Collection;
        }

        $components = new Collection;

        foreach ($parentTicketType->components as $component) {
            $componentTicketType = $component->componentTicketType;

            if ($componentTicketType === null) {
                continue;
            }

            for ($index = 0; $index < $component->quantity * $multiplier; $index++) {
                $components->push($componentTicketType);
            }
        }

        return $components;
    }
}
