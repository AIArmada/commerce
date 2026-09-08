<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Actions;

use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Collection;

final class ExpandTicketTypeComponentsAction
{
    /** @return Collection<int, TicketType> */
    public function handle(TicketType $parentTicketType, int $multiplier = 1): Collection
    {
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
