<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

interface Reactable
{
    /** @return array<string> */
    public function allowedReactionTypes(): array;

    public function allowsMultipleReactionTypesFromSameReactor(): bool;
}
