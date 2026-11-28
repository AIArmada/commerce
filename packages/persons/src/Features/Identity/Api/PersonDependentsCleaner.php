<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Identity\Api;

interface PersonDependentsCleaner
{
    public function cleanup(string $personId, string $personType): void;
}
